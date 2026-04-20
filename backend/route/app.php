<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\facade\Route;

Route::group('api', function () {
    Route::post('auth/login', '\app\api\controller\Auth@login');
    Route::post('auth/register', '\app\api\controller\Auth@register');
    Route::get('auth/profile', '\app\api\controller\Auth@profile');
    Route::put('auth/profile', '\app\api\controller\Auth@updateProfile');
    Route::post('auth/logout', '\app\api\controller\Auth@logout');

    Route::get('departments', '\app\api\controller\Department@index');

    Route::get('dashboard/summary', '\app\api\controller\Dashboard@summary');
    Route::get('dashboard/factory-board', '\app\api\controller\Dashboard@factoryBoard');

    Route::get('lookups/staff', '\app\api\controller\Lookup@staff');
    Route::get('lookups', '\app\api\controller\Lookup@enums');
    Route::get('suppliers', '\app\api\controller\Supplier@index');
    Route::get('reimburse', '\app\api\controller\Reimburse@index');
    Route::post('reimburse', '\app\api\controller\Reimburse@save');

    Route::get('orders/:id/progress', '\app\api\controller\Order@progress');
    Route::post('orders/:id/costs', '\app\api\controller\Order@addCost');
    Route::post('orders/:id/documents', '\app\api\controller\Order@addDocument');
    Route::get('orders/:id', '\app\api\controller\Order@read');
    Route::put('orders/:id', '\app\api\controller\Order@update');
    Route::get('orders', '\app\api\controller\Order@index');
    Route::post('orders', '\app\api\controller\Order@save');

    Route::post('tasks/:id/procurement', '\app\api\controller\Task@updateProcurement');
    Route::post('tasks/:id/assign', '\app\api\controller\Task@assign');
    Route::post('tasks/:id/status', '\app\api\controller\Task@updateStatus');
    Route::get('tasks/:id', '\app\api\controller\Task@read');
    Route::get('tasks', '\app\api\controller\Task@index');
    Route::post('tasks', '\app\api\controller\Task@save');

    Route::get('intent-orders', '\app\api\controller\IntentOrder@index');
    Route::post('intent-orders', '\app\api\controller\IntentOrder@save');
    Route::put('intent-orders/:id', '\app\api\controller\IntentOrder@update');
    Route::get('intent-orders/:id', '\app\api\controller\IntentOrder@read');

    Route::rule('announcements/:id/read', '\app\api\controller\Announcement@markRead', 'GET|POST');
    Route::get('announcements', '\app\api\controller\Announcement@index');
    Route::post('announcements', '\app\api\controller\Announcement@save');

    Route::rule('notifications/:id/read', '\app\api\controller\Notification@markRead', 'GET|POST');
    Route::get('notifications', '\app\api\controller\Notification@index');
    Route::post('notifications', '\app\api\controller\Notification@save');

    Route::get('messages/unread-count', '\app\api\controller\Message@unreadCount');

    Route::get('chat/conversations', '\app\api\controller\Chat@conversations');
    Route::get('chat/rooms/:id/messages', '\app\api\controller\Chat@messages');
    Route::post('chat/rooms/:id/messages', '\app\api\controller\Chat@sendMessage');
    Route::post('chat/rooms/:id/read', '\app\api\controller\Chat@markRead');
    Route::post('chat/rooms', '\app\api\controller\Chat@create');

    Route::get('attendance/rules', '\app\api\controller\Attendance@rules');
    Route::post('attendance/checkin', '\app\api\controller\Attendance@checkin');
    Route::get('attendance/records', '\app\api\controller\Attendance@records');
    Route::get('location/reverse', '\app\api\controller\Location@reverse');

    Route::get('factory-visits', '\app\api\controller\FactoryVisit@index');
    Route::post('factory-visits', '\app\api\controller\FactoryVisit@save');
    Route::put('factory-visits/:id', '\app\api\controller\FactoryVisit@update');

    Route::get('leave', '\app\api\controller\Leave@index');
    Route::post('leave', '\app\api\controller\Leave@save');
    Route::post('leave/:id/approve', '\app\api\controller\Leave@approve');

    Route::get('voltages', '\app\api\controller\Voltage@index');
    Route::get('currencies', '\app\api\controller\Currency@index');

    Route::post('upload', '\app\api\controller\Upload@save');

    Route::group('admin', function () {
        Route::post('auth/login', '\app\admin\controller\Auth@login');
        Route::get('auth/profile', '\app\admin\controller\Auth@profile');
        Route::post('auth/logout', '\app\admin\controller\Auth@logout');

        Route::get('departments', '\app\admin\controller\Department@index');
        Route::post('departments', '\app\admin\controller\Department@save');
        Route::put('departments/:id', '\app\admin\controller\Department@update');
        Route::delete('departments/:id', '\app\admin\controller\Department@delete');

        Route::get('suppliers', '\app\admin\controller\Supplier@index');
        Route::post('suppliers', '\app\admin\controller\Supplier@save');
        Route::put('suppliers/:id', '\app\admin\controller\Supplier@update');
        Route::delete('suppliers/:id', '\app\admin\controller\Supplier@delete');
        Route::get('reimburse', '\app\admin\controller\Reimburse@index');
        Route::post('reimburse/:id/status', '\app\admin\controller\Reimburse@updateStatus');
        Route::get('leave', '\app\admin\controller\Leave@index');
        Route::post('leave/:id/status', '\app\admin\controller\Leave@updateStatus');

        Route::get('users', '\app\admin\controller\User@index');
        Route::post('users/:id/approve', '\app\admin\controller\User@approve');
        Route::post('users/:id/reject', '\app\admin\controller\User@reject');

        Route::get('voltages', '\app\admin\controller\Voltage@index');
        Route::post('voltages', '\app\admin\controller\Voltage@save');
        Route::put('voltages/:id', '\app\admin\controller\Voltage@update');
        Route::delete('voltages/:id', '\app\admin\controller\Voltage@delete');

        Route::get('currencies', '\app\admin\controller\Currency@index');
        Route::post('currencies', '\app\admin\controller\Currency@save');
        Route::put('currencies/:id', '\app\admin\controller\Currency@update');
        Route::delete('currencies/:id', '\app\admin\controller\Currency@delete');
    });
});
