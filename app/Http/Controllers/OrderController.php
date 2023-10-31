<?php

namespace App\Http\Controllers;

use Midtrans\Snap;
use Midtrans\Config;

// require_once dirname(__FILE__) . '/../../../vendor/midtrans/midtrans-php/Midtrans.php';
use App\Models\Order;
use Illuminate\Support\Str;
use Illuminate\Http\Request;


class OrderController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->input('user_id');
        $orders = Order::query();

        $orders->when($userId, function ($query) use ($userId) {
            return $query->where('user_id', '=', $userId);
        });

        return response()->json([
            'status' => 'success',
            'data' => $orders->get()
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->input('user');
        $course = $request->input('course');

        $order = Order::create([
            'user_id' => $user['id'],
            'course_id' => $course['id'],
        ]);

        $transactionDetail = [
            'order_id' => $order->id . '-' . Str::random(5),
            'gross_amount' => $course['price'],
        ];

        $itemDetails = [
            [
                'id' => $course['id'],
                'price' => $course['price'],
                'quantity' => 1,
                'name' => $course['name'],
                'brand' => 'yhotie',
                'category' => 'online course'
            ]
        ];

        $customerDetail = [
            'first_name' => $user['name'],
            'email' => $user['email']
        ];
        /**
         * membuat parameter untuk mendapatkan snap Url
         *
         */
        $midtransParams = [
            'transaction_details' => $transactionDetail,
            'item_details' => $itemDetails,
            'customer_details' => $customerDetail,
        ];

        // generate snap url
        $midtransSnapUrl = $this->getMidtransSnapurl($midtransParams);

        // update order
        $order->snap_url = $midtransSnapUrl;

        $order->metadata = [
            'course_id' => $course['id'],
            'course_price' => $course['price'],
            'course_name' => $course['name'],
            'course_thumbnail' => $course['thumbnail'],
            'course_level' => $course['level'],
        ];

        $order->save();

        return response()->json([
            'status' => 'success',
            'data' => $order
        ]);
    }

    public function getMidtransSnapUrl($params)
    {
        Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        Config::$isProduction = (bool) env('MIDTRANS_PRODUCTION');
        Config::$isSanitized = true; // default
        Config::$is3ds = (bool) env('MIDTRANS_3DS');

        $snapUrl = Snap::createTransaction($params)->redirect_url;
        return $snapUrl;
    }
}
