<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\SolicitudAmistad;
use App\Models\Amistad;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Buscar usuarios por user_code.
     */
    public function buscar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_code' => 'required|string',
        ]);

        try {
            $authId = Auth::id();
            Log::info('Iniciando búsqueda de usuario', [
                'user_code' => $validated['user_code'],
                'auth_id' => $authId ?? 'No autenticado',
                'request' => $request->all(),
                'method' => $request->method(),
                'headers' => $request->headers->all(),
            ]);

            $users = User::with('datos')
                ->where('user_code', '=', $validated['user_code'])
                ->where('estado', 1)
                ->when($authId, function ($query) use ($authId) {
                    return $query->where('idUsuario', '!=', $authId); // Excluir usuario autenticado
                })
                ->get();

            if ($users->isEmpty()) {
                Log::info('Usuario no encontrado o es el usuario autenticado', [
                    'user_code' => $validated['user_code'],
                    'auth_id' => $authId ?? 'No autenticado',
                ]);
                return response()->json(['message' => 'Usuario no encontrado'], 404);
            }

            $result = $users->map(function ($user) use ($authId) {
                if (!$user->datos) {
                    Log::warning('Usuario sin datos asociados', [
                        'idUsuario' => $user->idUsuario,
                        'user_code' => $user->user_code,
                        'idDatos' => $user->idDatos,
                    ]);
                }

                // Verificar si ya son amigos en la tabla amistades
                $friendshipStatus = null;
                if ($authId) {
                    $existingFriendship = Amistad::where(function ($query) use ($authId, $user) {
                        $query->where('idUsuario', $authId)->where('idAmigo', $user->idUsuario);
                    })->orWhere(function ($query) use ($authId, $user) {
                        $query->where('idUsuario', $user->idUsuario)->where('idAmigo', $authId);
                    })->first();

                    if ($existingFriendship) {
                        $friendshipStatus = [
                            'isFriend' => true, // Indica que ya son amigos
                        ];
                    } else {
                        // Verificar si hay una solicitud de amistad existente
                        $existingRequest = SolicitudAmistad::where(function ($query) use ($authId, $user) {
                            $query->where('idUsuario', $authId)->where('idAmigo', $user->idUsuario);
                        })->orWhere(function ($query) use ($authId, $user) {
                            $query->where('idUsuario', $user->idUsuario)->where('idAmigo', $authId);
                        })->first();

                        if ($existingRequest) {
                            $friendshipStatus = [
                                'status' => $existingRequest->status,
                                'isSender' => $existingRequest->idUsuario === $authId,
                                'isFriend' => false,
                            ];
                        } else {
                            $friendshipStatus = [
                                'isFriend' => false,
                            ];
                        }
                    }
                }

                return [
                    'idUsuario' => $user->idUsuario,
                    'nombre' => $user->datos ? $user->datos->nombre : 'Sin nombre',
                    'user_code' => $user->user_code,
                    'perfil' => $user->datos ? $user->datos->perfil : null,
                    'friendshipStatus' => $friendshipStatus, // Estado de la solicitud o amistad
                ];
            });

            Log::info('Usuarios encontrados', [
                'user_code' => $validated['user_code'],
                'auth_id' => $authId ?? 'No autenticado',
                'result' => $result->toArray(),
            ]);

            return response()->json(['users' => $result], 200);
        } catch (\Exception $e) {
            Log::error('Error en buscar usuario: ' . $e->getMessage(), [
                'user_code' => $validated['user_code'],
                'auth_id' => $authId ?? 'No autenticado',
                'request' => $request->all(),
                'method' => $request->method(),
                'headers' => $request->headers->all(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Error al buscar usuario'], 500);
        }
    }

    // El método agregarAmigo permanece sin cambios
    public function agregarAmigo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'friend_id' => 'required|exists:usuarios,idUsuario',
        ]);

        try {
            $idUsuario = Auth::id();
            if (!$idUsuario) {
                Log::error('No hay usuario autenticado en agregar amigo', [
                    'friend_id' => $validated['friend_id'],
                    'request' => $request->all(),
                ]);
                return response()->json(['message' => 'Debes iniciar sesión para enviar solicitudes'], 401);
            }

            $idAmigo = $validated['friend_id'];

            if ($idUsuario === $idAmigo) {
                return response()->json(['message' => 'No puedes enviarte una solicitud de amistad a ti mismo'], 400);
            }

            // Verificar si hay una solicitud pendiente o aceptada
            $existingRequest = SolicitudAmistad::where(function ($query) use ($idUsuario, $idAmigo) {
                $query->where('idUsuario', $idUsuario)->where('idAmigo', $idAmigo);
            })->orWhere(function ($query) use ($idUsuario, $idAmigo) {
                $query->where('idUsuario', $idAmigo)->where('idAmigo', $idUsuario);
            })->whereIn('status', ['0', '1'])->first();

            if ($existingRequest) {
                $message = $existingRequest->status === '0' 
                    ? 'Ya existe una solicitud de amistad pendiente' 
                    : 'Ya son amigos';
                return response()->json(['message' => $message], 400);
            }

            // Si la solicitud fue rechazada (status = 2), eliminarla para permitir una nueva
            SolicitudAmistad::where(function ($query) use ($idUsuario, $idAmigo) {
                $query->where('idUsuario', $idUsuario)->where('idAmigo', $idAmigo);
            })->orWhere(function ($query) use ($idUsuario, $idAmigo) {
                $query->where('idUsuario', $idAmigo)->where('idAmigo', $idUsuario);
            })->where('status', '2')->delete();

            // Crear nueva solicitud
            SolicitudAmistad::create([
                'idUsuario' => $idUsuario,
                'idAmigo' => $idAmigo,
                'status' => '0', // Pendiente
            ]);

            Log::info('Solicitud de amistad creada', [
                'idUsuario' => $idUsuario,
                'idAmigo' => $idAmigo,
            ]);

            return response()->json(['message' => 'Solicitud de amistad enviada con éxito'], 200);
        } catch (\Exception $e) {
            Log::error('Error en agregar amigo: ' . $e->getMessage(), [
                'user_id' => $idUsuario ?? 'No autenticado',
                'friend_id' => $validated['friend_id'],
                'request' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Error al enviar solicitud de amistad'], 500);
        }
    }
}