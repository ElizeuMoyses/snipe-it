<?php

namespace App\Http\Controllers;

use App\Models\CheckoutAcceptance;
use App\Models\User;
use App\Models\Asset;
use App\Models\License;
use App\Models\LicenseSeat;
use App\Models\Accessory;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\Actionlog;
use App\Rules\ValidCpf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PublicEulaController extends Controller
{
    public function __construct()
    {
        // Verificar HTTPS em produção (desabilitado temporariamente para debug)
        /*
        if (app()->environment('production') && !request()->secure()) {
            Log::warning('HTTPS required for EULA signing in production', [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'url' => request()->fullUrl()
            ]);
            abort(403, 'HTTPS é obrigatório para assinatura de termos.');
        }
        */
    }

    /**
     * Show Step 1: Data validation form
     */
    public function showStep1($token)
    {
        try {
            // Log de acesso ao token com mais detalhes
            Log::info('EULA Step 1 access attempt - DETAILED', [
                'token' => substr($token, 0, 8) . '...',
                'token_full' => $token,
                'token_length' => strlen($token),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'url' => request()->fullUrl(),
                'method' => request()->method(),
                'headers' => request()->headers->all()
            ]);

            // Verificar se o token existe no banco ANTES da validação
            $tokenExists = CheckoutAcceptance::where('token', $token)->exists();
            Log::info('Token existence check', [
                'token' => substr($token, 0, 8) . '...',
                'exists' => $tokenExists,
                'total_tokens_in_db' => CheckoutAcceptance::whereNotNull('token')->count()
            ]);

            $acceptance = $this->validateToken($token);
            
            if (!$acceptance) {
                Log::warning('Invalid token access attempt', [
                    'token' => substr($token, 0, 8) . '...',
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent()
                ]);

                return view('public.eula.error', [
                    'errorType' => 'invalid_token',
                    'message' => 'Link inválido ou expirado.'
                ]);
            }

            // Check if blocked
            if ($acceptance->isBlocked()) {
                Log::warning('Blocked token access attempt', [
                    'token' => substr($token, 0, 8) . '...',
                    'checkout_acceptance_id' => $acceptance->id,
                    'failed_attempts' => $acceptance->failed_attempts,
                    'blocked_until' => $acceptance->blocked_until,
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent()
                ]);

                $blockDuration = config('eula.block_duration_minutes', 30);
                return view('public.eula.error', [
                    'errorType' => 'blocked',
                    'message' => "Muitas tentativas incorretas. Tente novamente em {$blockDuration} minutos."
                ]);
            }

            $user = $acceptance->assignedTo;

            // Log de acesso bem-sucedido
            Log::info('EULA Step 1 access successful', [
                'checkout_acceptance_id' => $acceptance->id,
                'user_id' => $user->id,
                'user_name' => $user->getFullNameAttribute(),
                'ip' => request()->ip()
            ]);

            // Registrar no actionlog do Snipe-IT
            $this->logAction($acceptance, 'eula_step1_access', 'Acesso à etapa 1 de assinatura de EULA');
            
            $masked_cpf = $this->maskCpf($user->employee_num);
            $masked_name = $this->maskName($user->first_name . ' ' . $user->last_name);

            return view('public.eula.step1', [
                'token' => $token,
                'user' => $user,
                'acceptance' => $acceptance,
                'masked_cpf' => $masked_cpf,
                'masked_name' => $masked_name
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in showStep1', [
                'token' => substr($token, 0, 8) . '...',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => request()->ip()
            ]);

            return view('public.eula.error', [
                'errorType' => 'system_error',
                'message' => 'Erro interno do sistema.'
            ]);
        }
    }

    /**
     * Validate Step 1: Process CPF and name validation
     */
    public function validateStep1(Request $request, $token)
    {
        try {
            Log::info('EULA Step 1 validation attempt', [
                'token' => substr($token, 0, 8) . '...',
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'cpf_provided' => !empty($request->cpf),
                'name_provided' => !empty($request->full_name)
            ]);

            $acceptance = $this->validateToken($token);
            
            if (!$acceptance) {
                Log::warning('Invalid token in validation step', [
                    'token' => substr($token, 0, 8) . '...',
                    'ip' => request()->ip()
                ]);

                return redirect()->route('eula.sign.step1', $token)
                    ->withErrors(['token' => 'Link inválido ou expirado.']);
            }

            // Check if blocked
            if ($acceptance->isBlocked()) {
                Log::warning('Blocked token validation attempt', [
                    'token' => substr($token, 0, 8) . '...',
                    'checkout_acceptance_id' => $acceptance->id,
                    'failed_attempts' => $acceptance->failed_attempts,
                    'blocked_until' => $acceptance->blocked_until,
                    'ip' => request()->ip()
                ]);

                $blockDuration = config('eula.block_duration_minutes', 30);
                return view('public.eula.error', [
                    'errorType' => 'blocked',
                    'message' => "Muitas tentativas incorretas. Tente novamente em {$blockDuration} minutos."
                ]);
            }

            $request->validate([
                'cpf' => ['required', 'string', new ValidCpf()],
                'full_name' => 'required|string|min:3|max:255'
            ]);

            $user = $acceptance->assignedTo;
            
            // Validate user data using the dedicated method
            $validationResult = $this->validateUserData($request->cpf, $request->full_name, $user);
            
            if (!$validationResult['valid']) {
                Log::warning('User data validation failed', [
                    'token' => substr($token, 0, 8) . '...',
                    'checkout_acceptance_id' => $acceptance->id,
                    'user_id' => $user->id,
                    'errors' => $validationResult['errors'],
                    'current_failed_attempts' => $acceptance->failed_attempts,
                    'ip' => request()->ip()
                ]);

                $acceptance->incrementFailedAttempts();

                // Registrar tentativa inválida no actionlog
                $this->logAction($acceptance, 'eula_validation_failed', 'Tentativa de validação de dados inválida');
                
                // Check if user is now blocked after incrementing attempts
                if ($acceptance->isBlocked()) {
                    Log::warning('Token blocked after failed validation attempts', [
                        'token' => substr($token, 0, 8) . '...',
                        'checkout_acceptance_id' => $acceptance->id,
                        'total_failed_attempts' => $acceptance->failed_attempts,
                        'blocked_until' => $acceptance->blocked_until,
                        'ip' => request()->ip()
                    ]);

                    // Registrar bloqueio no actionlog
                    $this->logAction($acceptance, 'eula_token_blocked', 'Token bloqueado por excesso de tentativas inválidas');

                    $blockDuration = config('eula.block_duration_minutes', 30);
                    return view('public.eula.error', [
                        'errorType' => 'blocked',
                        'message' => "Muitas tentativas incorretas. Tente novamente em {$blockDuration} minutos."
                    ]);
                }
                
                return redirect()->route('eula.sign.step1', $token)
                    ->withErrors($validationResult['errors']);
            }

            // Reset failed attempts on successful validation
            $acceptance->failed_attempts = 0;
            $acceptance->blocked_until = null;
            $acceptance->save();

            Log::info('EULA Step 1 validation successful', [
                'checkout_acceptance_id' => $acceptance->id,
                'user_id' => $user->id,
                'user_name' => $user->getFullNameAttribute(),
                'ip' => request()->ip()
            ]);

            // Registrar validação bem-sucedida no actionlog
            $this->logAction($acceptance, 'eula_validation_success', 'Dados pessoais validados com sucesso');

            return redirect()->route('eula.sign.step2', $token);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Erros de validação do Laravel (CPF inválido, campos obrigatórios, etc)
            Log::warning('Validation exception in validateStep1', [
                'token' => substr($token, 0, 8) . '...',
                'errors' => $e->errors(),
                'ip' => request()->ip()
            ]);
            
            return redirect()->route('eula.sign.step1', $token)
                ->withErrors($e->errors())
                ->withInput();
                
        } catch (\Exception $e) {
            Log::error('Error in validateStep1', [
                'token' => substr($token, 0, 8) . '...',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => request()->ip()
            ]);

            return redirect()->route('eula.sign.step1', $token)
                ->withErrors(['system' => 'Ocorreu um erro ao processar seus dados. Por favor, tente novamente. Se o problema persistir, entre em contato com o suporte.'])
                ->withInput();
        }
    }

    /**
     * Show Step 2: EULA visualization and acceptance
     */
    public function showStep2($token)
    {
        try {
            Log::info('EULA Step 2 access attempt - DETAILED', [
                'token' => substr($token, 0, 8) . '...',
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            $acceptance = $this->validateToken($token);
            
            if (!$acceptance) {
                Log::warning('Invalid token in step 2', [
                    'token' => substr($token, 0, 8) . '...',
                    'ip' => request()->ip()
                ]);

                return view('public.eula.error', [
                    'errorType' => 'invalid_token',
                    'message' => 'Link inválido ou expirado.'
                ]);
            }

            Log::info('Token validated successfully in step 2', [
                'acceptance_id' => $acceptance->id,
                'user_id' => $acceptance->assignedTo->id
            ]);

            $item = $acceptance->checkoutable;
            
            Log::info('Item retrieved', [
                'item_type' => get_class($item),
                'item_id' => $item->id
            ]);

            // Buscar EULA seguindo a hierarquia correta
            $eula = $this->getEulaText($item);

            Log::info('EULA Step 2 access successful', [
                'checkout_acceptance_id' => $acceptance->id,
                'user_id' => $acceptance->assignedTo->id,
                'item_type' => get_class($item),
                'item_id' => $item->id,
                'eula_length' => strlen($eula),
                'ip' => request()->ip()
            ]);

            // Registrar acesso ao EULA no actionlog
            try {
                $this->logAction($acceptance, 'eula_step2_access', 'Acesso à visualização do termo EULA');
            } catch (\Exception $logError) {
                Log::error('Error logging action', ['error' => $logError->getMessage()]);
            }

            return view('public.eula.step2', [
                'token' => $token,
                'acceptance' => $acceptance,
                'item' => $item,
                'eula' => $eula
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in showStep2 - DETAILED', [
                'token' => substr($token, 0, 8) . '...',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'ip' => request()->ip()
            ]);

            return response()->json([
                'error' => 'Erro interno do sistema',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Process Step 2: Handle EULA acceptance or decline
     */
    public function processStep2(Request $request, $token)
    {
        try {
            Log::info('EULA Step 2 processing attempt - DETAILED', [
                'token' => substr($token, 0, 8) . '...',
                'action' => $request->action,
                'all_request_data' => $request->all(),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            $acceptance = $this->validateToken($token);
            
            if (!$acceptance) {
                Log::warning('Invalid token in step 2 processing', [
                    'token' => substr($token, 0, 8) . '...',
                    'ip' => request()->ip()
                ]);

                return response()->json([
                    'error' => 'Token inválido ou expirado',
                    'token' => substr($token, 0, 8) . '...'
                ], 400);
            }

            Log::info('Token validated successfully in processStep2', [
                'acceptance_id' => $acceptance->id
            ]);

            // Validação mais flexível do action
            $action = $request->input('action');
            
            if (empty($action) || !in_array($action, ['accept', 'decline'])) {
                Log::error('Invalid action in processStep2', [
                    'action' => $action,
                    'has_action' => $request->has('action'),
                    'all_data' => $request->all(),
                    'method' => $request->method(),
                    'content_type' => $request->header('Content-Type')
                ]);
                
                return redirect()->route('eula.sign.step2', $token)
                    ->withErrors(['action' => 'Ação inválida. Use "Concordo" ou "Não Concordo".']);
            }

            if ($action === 'decline') {
                Log::info('Processing decline');
                
                // Register decline and finalize
                $acceptance->declined_at = now();
                $acceptance->token = null;
                $acceptance->token_expires_at = null;
                $acceptance->save();

                Log::info('EULA declined successfully', [
                    'checkout_acceptance_id' => $acceptance->id,
                    'user_id' => $acceptance->assignedTo->id
                ]);

                return view('public.eula.success', [
                    'message' => 'Termo recusado com sucesso.',
                    'type' => 'decline'
                ]);
            }

            Log::info('Processing accept - proceeding to signature', [
                'checkout_acceptance_id' => $acceptance->id,
                'user_id' => $acceptance->assignedTo->id
            ]);

            // If accepted, proceed to signature step
            return redirect()->route('eula.sign.step3', $token);
            
        } catch (\Exception $e) {
            Log::error('Error in processStep2 - DETAILED', [
                'token' => substr($token, 0, 8) . '...',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_data' => $request->all(),
                'ip' => request()->ip()
            ]);

            return response()->json([
                'error' => 'Erro interno do sistema',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Show Step 3: Digital signature interface
     */
    public function showStep3($token)
    {
        try {
            Log::info('EULA Step 3 access attempt', [
                'token' => substr($token, 0, 8) . '...',
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            $acceptance = $this->validateToken($token);
            
            if (!$acceptance) {
                Log::warning('Invalid token in step 3', [
                    'token' => substr($token, 0, 8) . '...',
                    'ip' => request()->ip()
                ]);

                return view('public.eula.error', [
                    'errorType' => 'invalid_token',
                    'message' => 'Link inválido ou expirado.'
                ]);
            }

            Log::info('EULA Step 3 access successful', [
                'checkout_acceptance_id' => $acceptance->id,
                'user_id' => $acceptance->assignedTo->id,
                'user_name' => $acceptance->assignedTo->getFullNameAttribute(),
                'ip' => request()->ip()
            ]);

            return view('public.eula.step3', [
                'token' => $token,
                'acceptance' => $acceptance
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in showStep3', [
                'token' => substr($token, 0, 8) . '...',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => request()->ip()
            ]);

            return view('public.eula.error', [
                'errorType' => 'system_error',
                'message' => 'Erro interno do sistema.'
            ]);
        }
    }

    /**
     * Process digital signature and finalize acceptance
     */
    public function processSignature(Request $request, $token)
    {
        try {
            Log::info('EULA signature processing attempt', [
                'token' => substr($token, 0, 8) . '...',
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'signature_data_length' => strlen($request->signature_data ?? '')
            ]);

            $acceptance = $this->validateToken($token);
            
            if (!$acceptance) {
                Log::warning('Invalid token in signature processing', [
                    'token' => substr($token, 0, 8) . '...',
                    'ip' => request()->ip()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Link inválido ou expirado.'
                ], 400);
            }

            $request->validate([
                'signature_data' => 'required|string',
                'signature_metadata' => 'nullable|string'
            ]);

            $signatureData = $request->signature_data;
            $signatureMetadata = $request->signature_metadata;
            
            // Validar se signature_data não está vazio
            if (empty($signatureData)) {
                Log::warning('Empty signature data provided', [
                    'checkout_acceptance_id' => $acceptance->id,
                    'ip' => request()->ip()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Assinatura é obrigatória.'
                ], 400);
            }

            // Decodificar base64 da assinatura
            $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $signatureData));
            
            if (!$imageData) {
                Log::warning('Invalid signature data format', [
                    'checkout_acceptance_id' => $acceptance->id,
                    'signature_data_prefix' => substr($signatureData, 0, 50),
                    'ip' => request()->ip()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Assinatura inválida.'
                ], 400);
            }

            // Verificar tamanho máximo da assinatura
            $maxSize = config('eula.max_signature_size', 1048576);
            if (strlen($imageData) > $maxSize) {
                Log::warning('Signature file too large', [
                    'checkout_acceptance_id' => $acceptance->id,
                    'signature_size' => strlen($imageData),
                    'ip' => request()->ip()
                ]);

                $maxSizeMB = round($maxSize / 1048576, 1);
                return response()->json([
                    'success' => false,
                    'message' => "Assinatura muito grande. Máximo permitido: {$maxSizeMB}MB."
                ], 400);
            }

            // Processar metadados da assinatura
            $metadata = [];
            if ($signatureMetadata) {
                try {
                    $metadata = json_decode($signatureMetadata, true);
                    $metadata['ip_address'] = request()->ip();
                    $metadata['timestamp'] = now()->toIso8601String();
                    
                    Log::info('Signature metadata captured', [
                        'checkout_acceptance_id' => $acceptance->id,
                        'total_strokes' => $metadata['totalStrokes'] ?? 0,
                        'device_type' => $metadata['device']['deviceType'] ?? 'unknown',
                        'ip' => $metadata['ip_address']
                    ]);
                } catch (\Throwable $metadataError) {
                    Log::warning('Error parsing signature metadata', [
                        'error' => $metadataError->getMessage(),
                        'checkout_acceptance_id' => $acceptance->id
                    ]);
                }
            }
            
            // Gerar nome único para arquivo
            $filename = 'signature_' . $token . '_' . time() . '.png';
            $signaturePath = 'signatures/' . $filename;

            // Salvar arquivo de assinatura
            try {
                // Usar storage padrão em vez de 'private'
                $storagePath = 'app/private_uploads/signatures/';
                $fullPath = storage_path($storagePath);
                
                // Criar diretório se não existir
                if (!file_exists($fullPath)) {
                    mkdir($fullPath, 0755, true);
                    Log::info('Created signatures directory', ['path' => $fullPath]);
                }
                
                // Salvar arquivo diretamente
                $filePath = $fullPath . $filename;
                file_put_contents($filePath, $imageData);
                
                Log::info('Signature file saved successfully', [
                    'checkout_acceptance_id' => $acceptance->id,
                    'filename' => $filename,
                    'file_size' => strlen($imageData),
                    'full_path' => $filePath,
                    'metadata_saved_to_db' => !empty($metadata),
                    'ip' => request()->ip()
                ]);
                
            } catch (\Throwable $storageError) {
                Log::error('Error saving signature file', [
                    'error' => $storageError->getMessage(),
                    'trace' => $storageError->getTraceAsString(),
                    'filename' => $filename,
                    'storage_path' => $storagePath ?? 'undefined'
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao salvar assinatura: ' . $storageError->getMessage()
                ], 500);
            }

            // Processar aceite completo usando o método do modelo
            $item = $acceptance->checkoutable;
            $eula = $this->getEulaText($item);
            
            Log::info('Processing acceptance', [
                'checkout_acceptance_id' => $acceptance->id,
                'item_type' => get_class($item),
                'item_id' => $item->id,
                'has_eula' => !empty($eula),
                'signature_filename' => $filename
            ]);
            
            // Processar aceite de forma simplificada
            try {
                // Atualizar campos básicos primeiro
                $acceptance->accepted_at = now();
                $acceptance->signature_filename = $filename;
                
                // Salvar metadados no banco de dados
                if (!empty($metadata)) {
                    // Salvar coordenadas GPS
                    if (isset($metadata['geolocation']['latitude']) && $metadata['geolocation']['latitude'] !== null) {
                        $acceptance->signature_latitude = $metadata['geolocation']['latitude'];
                        $acceptance->signature_longitude = $metadata['geolocation']['longitude'];
                    }
                    
                    // Salvar tipo de dispositivo
                    if (isset($metadata['device']['deviceType'])) {
                        $acceptance->signature_device_type = $metadata['device']['deviceType'];
                    }
                    
                    // Salvar IP
                    if (isset($metadata['ip_address'])) {
                        $acceptance->signature_ip = $metadata['ip_address'];
                    }
                }
                
                $acceptance->save();
                
                Log::info('Basic acceptance data updated', [
                    'checkout_acceptance_id' => $acceptance->id,
                    'accepted_at' => $acceptance->accepted_at,
                    'signature_filename' => $acceptance->signature_filename,
                    'signature_latitude' => $acceptance->signature_latitude ?? null,
                    'signature_longitude' => $acceptance->signature_longitude ?? null,
                    'signature_device_type' => $acceptance->signature_device_type ?? null,
                    'signature_ip' => $acceptance->signature_ip ?? null
                ]);
                
                // Tentar chamar método accept() se EULA existe
                if ($eula) {
                    try {
                        $acceptance->accept($filename, $eula);
                        Log::info('Full acceptance processed with EULA', [
                            'checkout_acceptance_id' => $acceptance->id
                        ]);
                    } catch (\Throwable $eulaError) {
                        Log::warning('Error in accept() method, but basic data saved', [
                            'error' => $eulaError->getMessage(),
                            'checkout_acceptance_id' => $acceptance->id
                        ]);
                        // Continue - dados básicos já foram salvos
                    }
                } else {
                    Log::info('Acceptance processed without EULA', [
                        'checkout_acceptance_id' => $acceptance->id
                    ]);
                }
                
            } catch (\Throwable $acceptError) {
                Log::error('Error processing acceptance', [
                    'error' => $acceptError->getMessage(),
                    'trace' => $acceptError->getTraceAsString(),
                    'checkout_acceptance_id' => $acceptance->id
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao processar aceite: ' . $acceptError->getMessage()
                ], 500);
            }

            // Invalidar token: token = null, token_expires_at = null
            try {
                $acceptance->token = null;
                $acceptance->token_expires_at = null;
                $acceptance->save();
                
                Log::info('Token invalidated successfully', [
                    'checkout_acceptance_id' => $acceptance->id
                ]);
            } catch (\Throwable $tokenError) {
                Log::error('Error invalidating token', [
                    'error' => $tokenError->getMessage(),
                    'checkout_acceptance_id' => $acceptance->id
                ]);
                // Continue mesmo se falhar - o importante é que a assinatura foi processada
            }

            Log::info('EULA signature completed successfully', [
                'checkout_acceptance_id' => $acceptance->id,
                'user_id' => $acceptance->assignedTo?->id,
                'user_name' => $acceptance->assignedTo?->getFullNameAttribute(),
                'item_type' => get_class($item),
                'item_id' => $item->id,
                'signature_filename' => $filename,
                'ip' => request()->ip()
            ]);

            // Registrar assinatura completa no actionlog
            try {
                $this->logAction($acceptance, 'eula_signature_completed', 'Assinatura digital do termo EULA finalizada com sucesso');
            } catch (\Throwable $logError) {
                Log::error('Error logging action', [
                    'error' => $logError->getMessage(),
                    'checkout_acceptance_id' => $acceptance->id
                ]);
                // Continue mesmo se o log falhar
            }

            // Redirecionar para página de sucesso
            Log::info('EULA signature process completed successfully', [
                'checkout_acceptance_id' => $acceptance->id,
                'user_id' => $acceptance->assignedTo?->id,
                'signature_filename' => $filename,
                'ip' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Assinatura processada com sucesso!',
                'redirect_url' => route('eula.success', ['token' => $token])
            ]);
            
        } catch (\Throwable $e) {
            Log::error('Error in processSignature', [
                'token' => substr($token, 0, 8) . '...',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => request()->ip()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno do sistema.'
            ], 500);
        }
    }

    /**
     * Validate token and return checkout acceptance
     */
    private function validateToken($token)
    {
        if (!$token) {
            Log::warning('Empty token provided', [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);
            return null;
        }

        // Log do token recebido para debug
        Log::info('Token validation attempt', [
            'token' => substr($token, 0, 8) . '...',
            'token_length' => strlen($token),
            'ip' => request()->ip()
        ]);

        $acceptance = CheckoutAcceptance::where('token', $token)->first();
        
        if (!$acceptance) {
            Log::warning('Token not found in database', [
                'token' => substr($token, 0, 8) . '...',
                'token_full' => $token, // Log completo para debug
                'ip' => request()->ip(),
                'total_tokens_in_db' => CheckoutAcceptance::whereNotNull('token')->count()
            ]);
            return null;
        }

        // Log detalhado do token encontrado
        Log::info('Token found in database', [
            'token' => substr($token, 0, 8) . '...',
            'checkout_acceptance_id' => $acceptance->id,
            'token_expires_at' => $acceptance->token_expires_at,
            'is_blocked' => $acceptance->isBlocked(),
            'is_pending' => $acceptance->isPending(),
            'ip' => request()->ip()
        ]);

        if (!$acceptance->isTokenValid()) {
            Log::warning('Token validation failed', [
                'token' => substr($token, 0, 8) . '...',
                'checkout_acceptance_id' => $acceptance->id,
                'token_expires_at' => $acceptance->token_expires_at,
                'is_blocked' => $acceptance->isBlocked(),
                'is_future' => $acceptance->token_expires_at ? $acceptance->token_expires_at->isFuture() : false,
                'ip' => request()->ip()
            ]);
            return null;
        }

        return $acceptance;
    }

    /**
     * Log action to Snipe-IT actionlog system
     */
    private function logAction($acceptance, $action, $note)
    {
        try {
            $user = $acceptance->assignedTo;
            $checkoutable = $acceptance->checkoutable;

            if (!$user || !$checkoutable) {
                Log::warning('Cannot log action: missing user or checkoutable', [
                    'action' => $action,
                    'checkout_acceptance_id' => $acceptance->id,
                    'has_user' => !is_null($user),
                    'has_checkoutable' => !is_null($checkoutable),
                ]);
                return;
            }

            $actionlog = new Actionlog();
            $actionlog->item_type = CheckoutAcceptance::class;
            $actionlog->item_id = $acceptance->id;
            $actionlog->created_by = $user->id;
            $actionlog->action_type = $action;
            $actionlog->note = $note;
            $actionlog->target_type = get_class($checkoutable);
            $actionlog->target_id = $checkoutable->id;
            $actionlog->created_at = now();
            $actionlog->remote_ip = request()->ip();
            $actionlog->user_agent = request()->userAgent();
            $actionlog->save();

            Log::info('Action logged to actionlog', [
                'actionlog_id' => $actionlog->id,
                'action_type' => $action,
                'checkout_acceptance_id' => $acceptance->id,
                'created_by' => $user->id
            ]);

        } catch (\Throwable $e) {
            Log::error('Failed to log action to actionlog', [
                'error' => $e->getMessage(),
                'action' => $action,
                'checkout_acceptance_id' => $acceptance->id
            ]);
        }
    }

    /**
     * Validate user data (CPF and name) against user record
     */
    private function validateUserData($inputCpf, $inputName, $user)
    {
        $errors = [];
        $valid = true;
        
        Log::debug('validateUserData started', [
            'inputCpf' => $inputCpf,
            'inputName' => $inputName,
            'user->employee_num' => $user->employee_num,
            'user->first_name' => $user->first_name,
            'user->last_name' => $user->last_name
        ]);

        // Validate CPF against user's employee number
        if (!$this->validateCpf($inputCpf, $user->employee_num)) {
            $errors['cpf'] = 'CPF não confere com o usuário do termo.';
            $valid = false;
        }
        
        // Validate name (case-insensitive, remove accents)
        $fullUserName = trim($user->first_name . ' ' . $user->last_name);
        if (!$this->validateName($inputName, $fullUserName)) {
            $errors['full_name'] = 'Nome não confere com o usuário do termo.';
            $valid = false;
        }
        
        Log::debug('validateUserData result', [
            'valid' => $valid,
            'errors' => $errors
        ]);

        return [
            'valid' => $valid,
            'errors' => $errors
        ];
    }

    /**
     * Validate CPF against user's employee number
     */
    private function validateCpf($inputCpf, $userEmployeeNum)
    {
        // Remove any formatting from both CPFs
        $inputCpf = preg_replace('/[^0-9]/', '', $inputCpf);
        $userCpf = preg_replace('/[^0-9]/', '', $userEmployeeNum);
        
        // Log para debug
        Log::info('CPF validation attempt', [
            'input_cpf' => substr($inputCpf, 0, 3) . '***' . substr($inputCpf, -2),
            'user_cpf' => $userCpf ? substr($userCpf, 0, 3) . '***' . substr($userCpf, -2) : 'empty',
            'input_length' => strlen($inputCpf),
            'user_length' => strlen($userCpf),
            'match' => $inputCpf === $userCpf
        ]);
        
        // Check if user has employee_num set
        if (empty($userCpf)) {
            Log::warning('User has no employee_num (CPF) set');
            return false;
        }
        
        // Basic validation - check if they match
        return $inputCpf === $userCpf;
    }

    /**
     * Validate name (case-insensitive, remove accents)
     */
    private function validateName($inputName, $userName)
    {
        // Normalize both names
        $inputNameNormalized = $this->normalizeName($inputName);
        $userNameNormalized = $this->normalizeName($userName);
        
        // Log para debug
        Log::info('Name validation attempt', [
            'input_name' => $inputName,
            'user_name' => $userName,
            'input_normalized' => $inputNameNormalized,
            'user_normalized' => $userNameNormalized,
            'match' => $inputNameNormalized === $userNameNormalized
        ]);
        
        return $inputNameNormalized === $userNameNormalized;
    }

    /**
     * Normalize name for comparison
     */
    private function normalizeName($name)
    {
        // Convert to lowercase and trim
        $name = strtolower(trim($name));
        
        // Remove accents using iconv
        $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        
        // Remove any remaining special characters that might have been transliterated
        $name = preg_replace('/[^a-z0-9\s]/', '', $name);
        
        // Remove extra spaces and normalize to single spaces
        $name = preg_replace('/\s+/', ' ', $name);
        
        // Final trim
        return trim($name);
    }

    /**
     * Show success page after signature completion
     */
    public function showSuccess($token = null)
    {
        try {
            Log::info('Accessing success page', [
                'token' => $token ? substr($token, 0, 8) . '...' : 'null',
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            return view('public.eula.success', [
                'message' => 'Termo assinado com sucesso!',
                'type' => 'accept',
                'token' => $token
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in showSuccess', [
                'token' => $token ? substr($token, 0, 8) . '...' : 'null',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => request()->ip()
            ]);
            
            return view('public.eula.error', [
                'message' => 'Erro interno do sistema. Tente novamente.',
                'error_code' => 'SUCCESS_ERROR'
            ]);
        }
    }

    /**
     * Get EULA text based on item type with caching
     * Hierarchy: Model EULA → Category EULA → Default EULA
     */
    private function getEulaText($item)
    {
        $itemType = get_class($item);
        $itemId = $item->id;
        $cacheKey = "eula_text_{$itemType}_{$itemId}";
        
        $cacheDuration = config('eula.eula_cache_duration', 3600);
        return \Illuminate\Support\Facades\Cache::remember($cacheKey, $cacheDuration, function () use ($item) {
            $eula = null;
            $source = 'none';
            
            // Para Assets: verificar Model EULA → Category EULA
            if ($item instanceof Asset) {
                // 1. Primeiro: EULA do Modelo
                if ($item->model && !empty($item->model->eula_text)) {
                    $eula = $item->model->eula_text;
                    $source = 'model';
                }
                // 2. Segundo: EULA da Categoria (se não encontrou no modelo)
                elseif ($item->model && $item->model->category && !empty($item->model->category->eula_text)) {
                    $eula = $item->model->category->eula_text;
                    $source = 'category';
                }
            }
            // Para outros tipos de item
            elseif ($item instanceof License && !empty($item->eula_text)) {
                $eula = $item->eula_text;
                $source = 'license';
            } elseif ($item instanceof Accessory && !empty($item->eula_text)) {
                $eula = $item->eula_text;
                $source = 'accessory';
            } elseif ($item instanceof Component && !empty($item->eula_text)) {
                $eula = $item->eula_text;
                $source = 'component';
            } elseif ($item instanceof Consumable && !empty($item->eula_text)) {
                $eula = $item->eula_text;
                $source = 'consumable';
            }
            
            // 3. Último: EULA Padrão (se não encontrou nenhum)
            if (empty($eula)) {
                $eula = $this->getDefaultEulaText($item);
                $source = 'default';
            }
            
            // Log da fonte do EULA para debug
            Log::info('EULA text retrieved', [
                'item_type' => get_class($item),
                'item_id' => $item->id,
                'item_name' => $item->name ?? 'N/A',
                'eula_source' => $source,
                'eula_length' => strlen($eula),
                'model_id' => $item instanceof Asset && $item->model ? $item->model->id : null,
                'category_id' => $item instanceof Asset && $item->model && $item->model->category ? $item->model->category->id : null
            ]);
            
            return $eula;
        });
    }

    /**
     * Mask CPF for user hint
     */
    private function maskCpf($cpf)
    {
        if (empty($cpf)) {
            return 'CPF não cadastrado';
        }
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        if (strlen($cpf) !== 11) {
            return 'CPF incompleto no cadastro';
        }
        // Ex: 09*.***.***-39
        return substr($cpf, 0, 2) . '*.***.***-' . substr($cpf, -2);
    }

    /**
     * Mask Name for user hint
     */
    private function maskName($name)
    {
        if (empty($name)) {
            return 'Nome não cadastrado';
        }
        $parts = explode(' ', trim($name));
        $maskedParts = [];
        
        foreach ($parts as $index => $part) {
            if ($index === 0) {
                // First name: reveal first 3 chars
                $revealStart = min(3, strlen($part));
                $maskedParts[] = substr($part, 0, $revealStart) . str_repeat('*', max(0, strlen($part) - $revealStart));
            } elseif ($index === count($parts) - 1) {
                // Last name: reveal last 3 chars
                $revealEnd = min(3, strlen($part));
                $maskedParts[] = str_repeat('*', max(0, strlen($part) - $revealEnd)) . substr($part, -$revealEnd);
            } else {
                // Middle names: all masked
                $maskedParts[] = str_repeat('*', strlen($part));
            }
        }
        
        return implode(' ', $maskedParts);
    }

    /**
     * Get default EULA text when no specific EULA is found
     */
    private function getDefaultEulaText($item)
    {
        $itemName = $item->name ?? 'Item';
        $itemType = 'item';
        
        if ($item instanceof \App\Models\Asset) {
            $itemType = 'ativo';
        } elseif ($item instanceof \App\Models\License) {
            $itemType = 'licença';
        } elseif ($item instanceof \App\Models\Accessory) {
            $itemType = 'acessório';
        } elseif ($item instanceof \App\Models\Component) {
            $itemType = 'componente';
        } elseif ($item instanceof \App\Models\Consumable) {
            $itemType = 'consumível';
        }

        return "TERMO DE ACEITE E RESPONSABILIDADE

Eu, ao assinar este documento, declaro que:

1. RECEBIMENTO: Recebi o {$itemType} \"{$itemName}\" em perfeitas condições de funcionamento.

2. RESPONSABILIDADE: Assumo total responsabilidade pela guarda, conservação e uso adequado do {$itemType} recebido.

3. OBRIGAÇÕES:
   - Utilizar o {$itemType} exclusivamente para fins profissionais
   - Manter o {$itemType} em local seguro e adequado
   - Comunicar imediatamente qualquer problema, dano ou perda
   - Não realizar modificações sem autorização prévia
   - Seguir todas as políticas de segurança da informação da empresa

4. DEVOLUÇÃO: Comprometo-me a devolver o {$itemType} quando solicitado, nas mesmas condições em que foi recebido, considerando o desgaste natural pelo uso.

5. RESPONSABILIZAÇÃO: Em caso de dano, perda ou uso inadequado, assumo a responsabilidade pelos custos de reparo ou substituição.

6. CONFORMIDADE: Declaro estar ciente das políticas internas da empresa e comprometo-me a cumpri-las integralmente.

Data: " . now()->format('d/m/Y') . "

Ao assinar digitalmente este documento, confirmo que li, compreendi e concordo com todos os termos acima descritos.";
    }

    /**
     * Generate acceptance PDF using existing Snipe-IT logic
     */
    private function generateAcceptancePdf($acceptance)
    {
        try {
            Log::info("PDF generation handled by accept() method for checkout_acceptance ID: {$acceptance->id}");
            // PDF generation is already handled by the accept() method in the model
            // No additional processing needed here
            
        } catch (\Exception $e) {
            Log::error('Error in PDF generation: ' . $e->getMessage());
        }
    }
}