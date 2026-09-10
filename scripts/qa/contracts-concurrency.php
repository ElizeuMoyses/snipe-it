<?php

// Run only against a dedicated synthetic database after artisan migrate.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Contract;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

if (DB::connection()->getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'snipeit_concurrency') {
    throw new RuntimeException('Use only the dedicated snipeit_concurrency MariaDB database.');
}

if (($argv[1] ?? '') === 'worker') {
    auth()->login(User::findOrFail((int) $argv[3]));
    file_put_contents($argv[4], 'ready');
    $contract = Contract::findOrFail((int) $argv[2]);
    if (in_array($argv[5] ?? '', ['payment', 'payment-ui'], true)) {
        $request = Illuminate\Http\Request::create('/synthetic-payment', 'POST', [
            'paid_value' => '10.00', 'payment_date' => '2026-09-10',
        ]);
        $controller = $argv[5] === 'payment-ui'
            ? App\Http\Controllers\ContractInstallmentsController::class
            : App\Http\Controllers\Api\ContractInstallmentsController::class;
        $response = app($controller)
            ->storePayment($request, $contract, $contract->installments()->firstOrFail()->id);
        echo $argv[5] === 'payment-ui' ? (session()->has('error') ? 422 : 200) : $response->getStatusCode();
    } else {
        echo $contract->generateInstallments();
    }
    exit;
}

if (! Setting::query()->exists()) {
    Setting::factory()->create();
}
$user = User::factory()->superuser()->create();
auth()->login($user);
$contract = Contract::factory()->oneTime()->create([
    'start_date' => '2026-01-31', 'total_value' => '100.00', 'total_installments' => 3,
]);
$workers = [];
$mode = in_array($argv[1] ?? '', ['payment', 'payment-ui'], true) ? $argv[1] : 'generation';
if ($mode !== 'generation') {
    $contract->generateInstallments();
}
$signals = [];
DB::beginTransaction();
try {
    Contract::whereKey($contract->id)->lockForUpdate()->firstOrFail();
    for ($i = 0; $i < 2; $i++) {
        $signal = tempnam(sys_get_temp_dir(), 'contract-worker-');
        $signals[] = $signal;
        $process = proc_open([PHP_BINARY, __FILE__, 'worker', (string) $contract->id, (string) $user->id, $signal, $mode],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($process)) {
            throw new RuntimeException('Could not launch worker');
        }
        fclose($pipes[0]);
        $workers[] = [$process, $pipes];
    }
    $deadline = microtime(true) + 30;
    do {
        $ready = count(array_filter($signals, fn ($signal) => file_get_contents($signal) === 'ready')) === 2;
        if (! $ready) {
            usleep(10000);
        }
    } while (! $ready && microtime(true) < $deadline);
    if (! $ready) {
        throw new RuntimeException('Workers did not reach generation barrier');
    }
    usleep(200000);
    foreach ($workers as [$process]) {
        if (! proc_get_status($process)['running']) {
            throw new RuntimeException('Worker bypassed contract lock or failed');
        }
    }
    DB::commit();
    $counts = [];
    foreach ($workers as [$process, $pipes]) {
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0 || ! ctype_digit(trim($output))) {
            throw new RuntimeException('Generation worker failed: '.$error);
        }
        $counts[] = (int) trim($output);
    }
    sort($counts);
    $expected = $mode !== 'generation' ? [200, 422] : [0, 3];
    if ($counts !== $expected || $contract->installments()->count() !== 3
        || (int) round($contract->installments()->sum('expected_value') * 100) !== 10000) {
        throw new RuntimeException('Concurrent generation duplicated or lost installments');
    }
    echo "PASS: two processes, serialized $mode, three installments, exact total.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    $failed = true;
} finally {
    if (DB::transactionLevel()) {
        DB::rollBack();
    }
    foreach ($workers as [$process]) {
        if (is_resource($process)) {
            proc_terminate($process);
        }
    }
    foreach ($signals as $signal) {
        unlink($signal);
    }
}
exit(isset($failed) ? 1 : 0);
