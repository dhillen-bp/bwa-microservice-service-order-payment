<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentLog;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function midtransHandler(Request $request)
    {
        // menghandle request
        $data = $request->all();

        // mengambil beberapa data di body
        $signatureKey = $data['signature_key'];
        $orderId = $data['order_id'];
        $statusCode = $data['status_code'];
        $grossAmount = $data['gross_amount'];
        $serverKey = env('MIDTRANS_SERVER_KEY');

        // membuat signature key dari data yang dikirim
        $mySignatureKey = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        $tansactionStatus = $data['transaction_status'];
        $type = $data['payment_type'];
        $fraudStatus = $data['fraud_status'];

        // pengecekan apakah signature yang dikirimkan sama
        if ($signatureKey !== $mySignatureKey) {
            return response()->json([
                'status' => 'error',
                'message' => 'invalid signature'
            ], 400);
        }

        // menggambil id order dari order id yang sudah di gabung
        $realOrderId = explode('-', $orderId);

        // pengecekan apakah data ada di data order
        $order = Order::find($realOrderId[0]);

        // pengecekan apakah data order ada atau tidak
        if (!$order) {
            return response()->json([
                'status'  => 'error',
                'message' => 'order not found'
            ], 404);
        }

        // jika order success maka tidak perlu melakukan perubahan
        if ($order->status === 'success') {
            return response()->json([
                'status'  => 'error',
                'message' => 'operation not permitted'
            ], 405);
        }

        if ($tansactionStatus == 'capture') {
            if ($fraudStatus == 'challenge') {
                // TODO set transaction status on your database to 'challenge'
                // and response with 200 OK

                $order->status = 'challenge';
            } else if ($fraudStatus == 'accept') {
                // TODO set transaction status on your database to 'success'
                // and response with 200 OK
                $order->status = 'success';
            }
        } else if ($tansactionStatus == 'settlement') {
            // TODO set transaction status on your database to 'success'
            // and response with 200 OK
            $order->status = 'success';
        } else if (
            $tansactionStatus == 'cancel' ||
            $tansactionStatus == 'deny' ||
            $tansactionStatus == 'expire'
        ) {
            // TODO set transaction status on your database to 'failure'
            // and response with 200 OK
            $order->status = 'failure';
        } else if ($tansactionStatus == 'pending') {
            // TODO set transaction status on your database to 'pending' / waiting payment
            // and response with 200 OK
            $order->status = 'pending';
        }

        $logsData = [
            'status' => $tansactionStatus,
            'raw_response' => json_encode($data),
            'order_id' => $realOrderId[0],
            'payment_type' => $type
        ];

        // menyimpan payment logs
        PaymentLog::create($logsData);

        // update order
        $order->save();

        // memberikan akses kelas ke user yang membayar
        if ($order->status === 'success') {
            // memberikan akses premium
            createPremiumAccess([
                'user_id' => $order->user_id,
                'course_id' => $order->course_id
            ]);
        }
        return response()->json('ok');
    }
}
