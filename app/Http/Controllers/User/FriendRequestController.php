<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAmistadRequest;
use App\Models\Amistad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\SolicitudAmistad;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class FriendRequestController extends Controller
{
    /**
     * Listar solicitudes de amistad enviadas por el usuario autenticado.
     */
    public function getSentRequests(Request $request): JsonResponse
    {
        try {
            $idUsuario = Auth::id();
            if (!$idUsuario) {
                Log::error('No hay usuario autenticado al listar solicitudes enviadas');
                return response()->json(['message' => 'No estás autenticado'], 401);
            }

            $sentRequests = SolicitudAmistad::where('idUsuario', $idUsuario)
                ->where('status', '0') // Solo solicitudes pendientes
                ->with(['amigo' => function ($query) {
                    $query->with('datos');
                }])
                ->get()
                ->map(function ($request) {
                    return [
                        'idSolicitudAmistad' => $request->idSolicitudAmistad,
                        'idAmigo' => $request->idAmigo,
                        'nombre' => $request->amigo->datos ? $request->amigo->datos->nombre : 'Sin nombre',
                        'user_code' => $request->amigo->user_code,
                        'perfil' => $request->amigo->datos ? $request->amigo->datos->perfil : null,
                        'created_at' => $request->created_at,
                    ];
                });

            Log::info('Solicitudes enviadas listadas', [
                'idUsuario' => $idUsuario,
                'total' => $sentRequests->count(),
            ]);

            return response()->json([
                'sentRequests' => $sentRequests,
                'message' => 'Solicitudes enviadas obtenidas con éxito'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al listar solicitudes enviadas: ' . $e->getMessage(), [
                'idUsuario' => $idUsuario ?? 'No autenticado',
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Error al listar solicitudes enviadas', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Listar solicitudes de amistad recibidas por el usuario autenticado.
     */
    public function getReceivedRequests(Request $request): JsonResponse
    {
        try {
            $idUsuario = Auth::id();
            if (!$idUsuario) {
                Log::error('No hay usuario autenticado al listar solicitudes recibidas');
                return response()->json(['message' => 'No estás autenticado'], 401);
            }

            $receivedRequests = SolicitudAmistad::where('idAmigo', $idUsuario)
                ->where('status', '0') // Solo solicitudes pendientes
                ->with(['usuario' => function ($query) {
                    $query->with('datos');
                }])
                ->get()
                ->map(function ($request) {
                    return [
                        'idSolicitudAmistad' => $request->idSolicitudAmistad,
                        'idUsuario' => $request->idUsuario,
                        'nombre' => $request->usuario->datos ? $request->usuario->datos->nombre : 'Sin nombre',
                        'user_code' => $request->usuario->user_code,
                        'perfil' => $request->usuario->datos ? $request->usuario->datos->perfil : null,
                        'created_at' => $request->created_at,
                    ];
                });

            Log::info('Solicitudes recibidas listadas', [
                'idUsuario' => $idUsuario,
                'total' => $receivedRequests->count(),
            ]);

            return response()->json(['receivedRequests' => $receivedRequests], 200);
        } catch (\Exception $e) {
            Log::error('Error al listar solicitudes recibidas: ' . $e->getMessage(), [
                'idUsuario' => $idUsuario ?? 'No autenticado',
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Error al listar solicitudes recibidas', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Aceptar una solicitud de amistad.
     */
    public function acceptRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'idSolicitudAmistad' => 'required|exists:solicitudes_amistad,idSolicitudAmistad',
        ]);

        try {
            $idUsuario = Auth::id();
            if (!$idUsuario) {
                Log::error('No hay usuario autenticado al aceptar solicitud');
                return response()->json(['message' => 'Debes iniciar sesión para aceptar solicitudes'], 401);
            }

            $requestFriend = SolicitudAmistad::where('idSolicitudAmistad', $validated['idSolicitudAmistad'])
                ->where('idAmigo', $idUsuario)
                ->where('status', '0')
                ->first();

            if (!$requestFriend) {
                Log::warning('Solicitud no encontrada o no válida para aceptar', [
                    'idSolicitudAmistad' => $validated['idSolicitudAmistad'],
                    'idUsuario' => $idUsuario,
                ]);
                return response()->json(['message' => 'La solicitud no existe o no eres el destinatario'], 403);
            }

            if ($requestFriend->idUsuario === $idUsuario) {
                Log::warning('Intento de aceptar solicitud propia', [
                    'idSolicitudAmistad' => $validated['idSolicitudAmistad'],
                    'idUsuario' => $idUsuario,
                ]);
                return response()->json(['message' => 'No puedes aceptar tu propia solicitud de amistad'], 403);
            }

            // Actualizar la solicitud a aceptada
            $requestFriend->update(['status' => '1']);

            // Crear la relación de amistad en la tabla amistades
            $existingFriendship = Amistad::where(function ($query) use ($idUsuario, $requestFriend) {
                $query->where('idUsuario', $idUsuario)->where('idAmigo', $requestFriend->idUsuario);
            })->orWhere(function ($query) use ($idUsuario, $requestFriend) {
                $query->where('idUsuario', $requestFriend->idUsuario)->where('idAmigo', $idUsuario);
            })->first();

            if (!$existingFriendship) {
                Amistad::create([
                    'idUsuario' => $idUsuario,
                    'idAmigo' => $requestFriend->idUsuario,
                ]);
            }

            Log::info('Solicitud de amistad aceptada y amistad creada', [
                'idSolicitudAmistad' => $validated['idSolicitudAmistad'],
                'idUsuario' => $idUsuario,
                'idAmigo' => $requestFriend->idUsuario,
            ]);

            return response()->json(['message' => '¡Solicitud de amistad aceptada con éxito!'], 200);
        } catch (\Exception $e) {
            Log::error('Error al aceptar solicitud: ' . $e->getMessage(), [
                'idUsuario' => $idUsuario ?? 'No autenticado',
                'idSolicitudAmistad' => $validated['idSolicitudAmistad'],
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Ocurrió un error al aceptar la solicitud'], 500);
        }
    }

        /**
     * Listar amigos del usuario autenticado.
     */
    public function getFriends(Request $request): JsonResponse
    {
        try {
            $idUsuario = Auth::id();
            if (!$idUsuario) {
                Log::error('No hay usuario autenticado al listar amigos');
                return response()->json(['message' => 'Debes iniciar sesión para ver tus amigos'], 401);
            }

            $friends = Amistad::where('idUsuario', $idUsuario)
                ->orWhere('idAmigo', $idUsuario)
                ->with(['usuario.datos', 'amigo.datos'])
                ->get()
                ->map(function ($friendship) use ($idUsuario) {
                    $friend = $friendship->idUsuario === $idUsuario ? $friendship->amigo : $friendship->usuario;
                    return [
                        'idUsuario' => $friend->idUsuario,
                        'nombre' => $friend->datos ? $friend->datos->nombre : 'Sin nombre',
                        'user_code' => $friend->user_code,
                        'perfil' => $friend->datos ? $friend->datos->perfil : null,
                        'created_at' => $friendship->created_at,
                    ];
                });

            Log::info('Amigos listados', [
                'idUsuario' => $idUsuario,
                'total' => $friends->count(),
            ]);

            return response()->json(['friends' => $friends], 200);
        } catch (\Exception $e) {
            Log::error('Error al listar amigos: ' . $e->getMessage(), [
                'idUsuario' => $idUsuario ?? 'No autenticado',
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Error al listar amigos'], 500);
        }
    }

    /**
     * Rechazar una solicitud de amistad.
     */
    public function rejectRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'idSolicitudAmistad' => 'required|exists:solicitudes_amistad,idSolicitudAmistad',
        ]);

        try {
            $idUsuario = Auth::id();
            if (!$idUsuario) {
                Log::error('No hay usuario autenticado al rechazar solicitud');
                return response()->json(['message' => 'No estás autenticado'], 401);
            }

            $requestFriend = SolicitudAmistad::where('idSolicitudAmistad', $validated['idSolicitudAmistad'])
                ->where('idAmigo', $idUsuario)
                ->where('status', '0')
                ->first();

            if (!$requestFriend) {
                Log::warning('Solicitud no encontrada o no válida para rechazar', [
                    'idSolicitudAmistad' => $validated['idSolicitudAmistad'],
                    'idUsuario' => $idUsuario,
                ]);
                return response()->json(['message' => 'Solicitud no encontrada o no eres el destinatario'], 403);
            }

            // Verificar explícitamente que el usuario autenticado no es el remitente
            if ($requestFriend->idUsuario === $idUsuario) {
                Log::warning('Intento de rechazar solicitud propia', [
                    'idSolicitudAmistad' => $validated['idSolicitudAmistad'],
                    'idUsuario' => $idUsuario,
                ]);
                return response()->json(['message' => 'No puedes rechazar tu propia solicitud'], 403);
            }

            $requestFriend->update(['status' => '2']); // Rechazada

            Log::info('Solicitud de amistad rechazada', [
                'idSolicitudAmistad' => $validated['idSolicitudAmistad'],
                'idUsuario' => $idUsuario,
                'idAmigo' => $requestFriend->idUsuario,
            ]);

            return response()->json(['message' => 'Solicitud de amistad rechazada con éxito'], 200);
        } catch (\Exception $e) {
            Log::error('Error al rechazar solicitud: ' . $e->getMessage(), [
                'idUsuario' => $idUsuario ?? 'No autenticado',
                'idSolicitudAmistad' => $validated['idSolicitudAmistad'],
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Error al rechazar solicitud', 'error' => $e->getMessage()], 500);
        }
    }
}   