<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\SolicitudAmistad;
use App\Models\Amistad;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    /**
     * Obtener el conteo de solicitudes de amistad pendientes (enviadas y recibidas).
     */
    public function getPendingRequestsCount(Request $request): JsonResponse
    {
        try {
            $idUsuario = Auth::id();
            if (!$idUsuario) {
                Log::error('No hay usuario autenticado al obtener conteo de solicitudes pendientes');
                return response()->json(['message' => 'Debes iniciar sesión'], 401);
            }

            $sentRequestsCount = SolicitudAmistad::where('idUsuario', $idUsuario)
                ->where('status', '0')
                ->count();

            $receivedRequestsCount = SolicitudAmistad::where('idAmigo', $idUsuario)
                ->where('status', '0')
                ->count();

            Log::info('Conteo de solicitudes pendientes obtenido', [
                'idUsuario' => $idUsuario,
                'sentRequestsCount' => $sentRequestsCount,
                'receivedRequestsCount' => $receivedRequestsCount,
            ]);

            return response()->json([
                'sentRequestsCount' => $sentRequestsCount,
                'receivedRequestsCount' => $receivedRequestsCount,
                'totalPendingRequests' => $sentRequestsCount + $receivedRequestsCount,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener conteo de solicitudes pendientes: ' . $e->getMessage(), [
                'idUsuario' => $idUsuario ?? 'No autenticado',
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Error al obtener conteo de solicitudes'], 500);
        }
    }

    /**
     * Obtener el conteo de amigos del usuario autenticado.
     */
    public function getFriendsCount(Request $request): JsonResponse
    {
        try {
            $idUsuario = Auth::id();
            if (!$idUsuario) {
                Log::error('No hay usuario autenticado al obtener conteo de amigos');
                return response()->json(['message' => 'Debes iniciar sesión'], 401);
            }

            $friendsCount = Amistad::where('idUsuario', $idUsuario)
                ->orWhere('idAmigo', $idUsuario)
                ->count();

            Log::info('Conteo de amigos obtenido', [
                'idUsuario' => $idUsuario,
                'friendsCount' => $friendsCount,
            ]);

            return response()->json(['friendsCount' => $friendsCount], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener conteo de amigos: ' . $e->getMessage(), [
                'idUsuario' => $idUsuario ?? 'No autenticado',
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Error al obtener conteo de amigos'], 500);
        }
    }
}