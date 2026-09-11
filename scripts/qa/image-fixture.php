<?php

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.connections.mysql.database') !== 'snipeit_image_test'
    || !str_starts_with((string) config('database.connections.mysql.host'), 'snipeit-qa-db-')
    || App\Models\Setting::query()->exists()
    || App\Models\User::query()->exists()) {
    throw new RuntimeException('Image fixture requires the empty disposable image-test database.');
}
$settings = new App\Models\Setting();
$settings->site_name = 'Synthetic image validation';
$settings->per_page = 20;
$settings->default_currency = 'BRL';
$settings->locale = 'pt-BR';
$settings->brand = 1;
$settings->ldap_enabled = 0;
$settings->saml_enabled = 0;
$settings->save();
$user = new App\Models\User();
$user->first_name = 'Synthetic';
$user->last_name = 'Image validation';
$user->username = 'image.validation';
$user->email = 'image.validation@example.test';
$user->password = Illuminate\Support\Facades\Hash::make(bin2hex(random_bytes(32)));
$user->activated = 1;
$user->permissions = json_encode(['superuser' => '1']);
$user->save();
echo "Synthetic settings and user created.\n";
