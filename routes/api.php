<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\User\FriendRequestController;
use App\Http\Controllers\User\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Rutas públicas (no requieren autenticación)

Route::post('/login', [AuthController::class, 'login']);

Route::post('/refresh', [AuthController::class, 'refresh']);

Route::post('/validate-refresh-token', [AuthController::class, 'validateRefreshToken']);

// RUTAS PARA X VALIDADA POR MIDDLEWARE AUTH (PARA TOKEN JWT) Y CHECKROLE (PARA VALIDAR ROL DEL TOKEN)
Route::middleware(['auth.jwt', 'checkRoleMW:usuario'])->group(function () { 

    //MODULO HOMEUSUARIO
    Route::post('/user/search', [UserController::class, 'buscar']);
        //CBXBUSCARUSUARIO
    Route::post('/user/friend/add', [UserController::class, 'agregarAmigo']);
    //SECCION DE SOLICITUDES DE AMISTAD
    Route::get('/friend/requests/sent', [FriendRequestController::class, 'getSentRequests']);
    Route::get('/friend/requests/received', [FriendRequestController::class, 'getReceivedRequests']);
    Route::post('/friend/requests/accept', [FriendRequestController::class, 'acceptRequest']);
    Route::post('/friend/requests/reject', [FriendRequestController::class, 'rejectRequest']);
    Route::get('/friends', [FriendRequestController::class, 'getFriends']);
    //NOTIFICATIONES
    Route::get('/notifications/pending-requests-count', [NotificationController::class, 'getPendingRequestsCount']);
    Route::get('/notifications/friends-count', [NotificationController::class, 'getFriendsCount']);
});

// RUTAS PARA ADMIN VALIDADA POR MIDDLEWARE AUTH (PARA TOKEN JWT) Y CHECKROLE (PARA VALIDAR ROL DEL TOKEN)
Route::middleware(['auth.jwt', 'checkRoleMW:admin'])->group(function () { 

  
  
});


// RUTAS PARA Roles Admin y Cliente
Route::middleware(['auth.jwt', 'checkRolesMW'])->group(function () { 

    Route::post('/logout', [AuthController::class, 'logout']);
  
});

        


