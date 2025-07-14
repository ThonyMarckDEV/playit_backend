<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Datos;
use App\Models\User;
use App\Models\UserCode;
use Firebase\JWT\JWT;
use Google_Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // Validar los datos de entrada
        $validator = Validator::make($request->all(), [
            'id_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            // Inicializar el cliente de Google
            $client = new Google_Client(['client_id' => config('services.google.client_id')]);
            $payload = $client->verifyIdToken($request->id_token);

            if (!$payload || empty($payload['email'])) {
                return response()->json([
                    'message' => 'Token de Google inválido o sin email',
                ], 401);
            }

            // Obtener datos del usuario desde el token
            $email = $payload['email'];
            $firstName = $payload['given_name'] ?? 'Usuario';
            $lastName = $payload['family_name'] ?? '';
            $apellidos = trim($lastName);
            $perfil = $payload['picture'] ?? null; // Google profile photo URL

            // Buscar o crear el registro en la tabla datos
            $dato = Datos::where('email', $email)->first();
            $isNewUser = false;

            if (!$dato) {
                $dato = Datos::create([
                    'nombre' => $firstName,
                    'apellidos' => $apellidos,
                    'email' => $email,
                    'perfil' => $perfil,
                ]);
                $isNewUser = true;
            } elseif ($perfil && $dato->perfil !== $perfil) {
                // Update perfil if it has changed
                $dato->update(['perfil' => $perfil]);
            }

            // Buscar o crear el usuario
            $user = User::whereHas('datos', function ($query) use ($email) {
                $query->where('email', $email);
            })->first();

            if (!$user && $isNewUser) {
                $user = User::create([
                    'password' => Hash::make(Str::random(16)), // PIN hasheado temporal
                    'idDatos' => $dato->idDatos,
                    'idRol' => 2, // Rol por defecto (usuario)
                    'user_code' => UserCode::generateUserCode(), // Generate unique user code
                    'estado' => 1,
                ]);
            } elseif ($user && $user->estado !== 1) {
                return response()->json([
                    'message' => 'Error: estado del usuario inactivo',
                ], 403);
            }

            // Generar tokens JWT
            $now = now()->timestamp;
            $expiresIn = config('jwt.ttl', 5) * 60; // 5 minutos para access token
            $refreshTTL = 7 * 24 * 60 * 60; // 7 días para refresh token
            $secret = config('jwt.secret');

            // Payload para el access token
            $accessPayload = [
                'iss' => config('app.url'),
                'iat' => $now,
                'exp' => $now + $expiresIn,
                'nbf' => $now,
                'jti' => Str::random(16),
                'sub' => $user->idUsuario,
                'prv' => sha1(config('app.key')),
                'rol' => $user->rol ? $user->rol->nombre : 'usuario',
                'nombre' => $dato->nombre ?? 'N/A',
                'email' => $dato->email,
                'perfil' => $dato->perfil,
                'user_code' => $user->user_code,
            ];

            // Payload para el refresh token
            $refreshPayload = [
                'iss' => config('app.url'),
                'iat' => $now,
                'exp' => $now + $refreshTTL,
                'nbf' => $now,
                'jti' => Str::random(16),
                'sub' => $user->idUsuario,
                'prv' => sha1(config('app.key')),
                'type' => 'refresh',
                'rol' => $user->rol ? $user->rol->nombre : 'usuario',
                'nombre' => $dato->nombre ?? 'N/A',
                'email' => $dato->email,
                'perfil' => $dato->perfil,
                'user_code' => $user->user_code,
            ];

            // Generar tokens usando Firebase JWT
            $accessToken = JWT::encode($accessPayload, $secret, 'HS256');
            $refreshToken = JWT::encode($refreshPayload, $secret, 'HS256');

            // Gestionar sesiones activas (máximo 1)
            $activeSessions = DB::table('refresh_tokens')
                ->where('idUsuario', $user->idUsuario)
                ->where('expires_at', '>', now())
                ->orderBy('created_at', 'asc')
                ->get();

            if ($activeSessions->count() >= 1) {
                DB::table('refresh_tokens')
                    ->where('idToken', $activeSessions->first()->idToken)
                    ->delete();
            }

            // Insertar nuevo refresh token
            $refreshTokenId = DB::table('refresh_tokens')->insertGetId([
                'idUsuario' => $user->idUsuario,
                'refresh_token' => $refreshToken,
                'ip_address' => $request->ip(),
                'device' => $request->userAgent() ?? 'Unknown',
                'expires_at' => now()->addSeconds($refreshTTL),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Retornar respuesta con tokens
            return response()->json([
                'message' => 'Login exitoso',
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'idRefreshToken' => $refreshTokenId,
            ], 200);
        } catch (\Google\Exception $e) {
            return response()->json([
                'message' => 'Error al verificar el token de Google: ' . $e->getMessage(),
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno del servidor: ' . $e->getMessage(),
            ], 500);
        }
    }

     // Método para refrescar el token
    public function refresh(Request $request)
    {
        // Validar el refresh token
        $validator = Validator::make($request->all(), [
            'refresh_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Refresh token inválido',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            // Verificar el token con Firebase JWT
            $secret = config('jwt.secret');
            $payload = \Firebase\JWT\JWT::decode($request->refresh_token, new \Firebase\JWT\Key($secret, 'HS256'));
            
            // Verificar que sea un token de refresco
            if (!isset($payload->type) || $payload->type !== 'refresh') {
                return response()->json([
                    'message' => 'El token proporcionado no es un token de refresco',
                ], 401);
            }
            
            // Obtener el ID de usuario
            $userId = $payload->sub;
            $user = User::find($userId);
            
            if (!$user) {
                return response()->json([
                    'message' => 'Usuario no encontrado',
                ], 404);
            }
            
            // Generar un nuevo token de acceso con Firebase JWT
            $now = time();
            $expiresIn = config('jwt.ttl') * 60;
            
            // Crear payload del token de acceso con custom claims del usuario
            $accessPayload = [
                'iss' => config('app.url'),
                'iat' => $now,
                'exp' => $now + $expiresIn,
                'nbf' => $now,
                'jti' => Str::random(16),
                'sub' => $user->idUsuario,
                'prv' => sha1(config('app.key')),
                'rol' => $user->rol ? $user->rol->nombre : 'usuario',
                'nombre' => $dato->nombre ?? 'N/A',
                'email' => $user->datos->email,
                'perfil' => $user->datos->perfil,
                'user_code' => $user->user_code,
            ];
            
            // Generar nuevo token de acceso usando Firebase JWT
            $newToken = \Firebase\JWT\JWT::encode($accessPayload, $secret, 'HS256');
            
            return response()->json([
                'message' => 'Token actualizado',
                'access_token' => $newToken,
                'token_type' => 'bearer',
                'expires_in' => $expiresIn
            ], 200);
            
        } catch (\Firebase\JWT\ExpiredException $e) {
            return response()->json([
                'message' => 'Refresh token expirado'
            ], 401);
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            return response()->json([
                'message' => 'Refresh token inválido'
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al procesar el token',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function validateRefreshToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'refresh_token_id' => 'required|integer',
            'userID' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'valid' => false,
                'message' => 'Datos inválidos'
            ], 400);
        }

        try {
            $refreshToken = DB::table('refresh_tokens')
                ->where('idToken', $request->refresh_token_id)
                ->where('idUsuario', $request->userID)
                ->first();

            if (!$refreshToken) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Token no válido o no autorizado'
                ], 401);
            }

            if ($refreshToken->expires_at && now()->greaterThan($refreshToken->expires_at)) {
                DB::table('refresh_tokens')
                    ->where('idToken', $request->refresh_token_id)
                    ->where('idUsuario', $request->userID)
                    ->delete();

                return response()->json([
                    'valid' => false,
                    'message' => 'Token expirado'
                ], 401);
            }

            return response()->json([
                'valid' => true,
                'message' => 'Token válido'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error validating refresh token: ' . $e->getMessage());
            return response()->json([
                'valid' => false,
                'message' => 'Error al validar el token'
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        $request->validate([
            'idToken' => 'required|integer|exists:refresh_tokens,idToken',
        ]);

        $deleted = DB::table('refresh_tokens')
            ->where('idToken', $request->idToken)
            ->delete();

        if ($deleted) {
            return response()->json([
                'message' => 'OK',
            ], 200);
        }

        return response()->json([
            'message' => 'Error: No se encontró el token de refresco',
        ], 404);
    }
}