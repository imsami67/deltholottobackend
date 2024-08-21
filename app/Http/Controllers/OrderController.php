<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use Carbon\Carbon;

class OrderController extends Controller
{
    
public function getOrderHistory(Request $request)
{
    try {
        $user = Auth()->user();
        $userIds = $request->input('user_ids');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $filterBy = $request->input('filter_by'); // assuming this is either 'today', 'yesterday', 'thisWeek', 'lastWeek', 'thisMonth'

        // Fetch users based on the provided user IDs
        $users = DB::table('users')->select('user_id', 'username', 'user_role')->whereIn('user_id', $userIds)->get();

        // Determine the date range based on the filter if fromDate and toDate are not provided
        if (!$fromDate || !$toDate) {
            $toDate = Carbon::now();

            switch ($filterBy) {
                case 'today':
                    $fromDate = Carbon::today();
                    break;
                case 'yesterday':
                    $fromDate = Carbon::yesterday();
                    $toDate = Carbon::yesterday()->endOfDay();
                    break;
                case 'thisWeek':
                    $fromDate = Carbon::now()->startOfWeek();
                    break;
                case 'lastWeek':
                    $fromDate = Carbon::now()->subWeek()->startOfWeek();
                    $toDate = Carbon::now()->subWeek()->endOfWeek();
                    break;
                case 'thisMonth':
                    $fromDate = Carbon::now()->startOfMonth();
                    break;
                default:
                    // If no valid filter is provided, use a default range or handle the error
                    $fromDate = Carbon::now()->subMonth(); // Example: default to the last month
                    break;
            }
        } else {
            // Convert fromDate and toDate to Carbon instances
            $fromDate = Carbon::createFromFormat('d M, Y', $fromDate)->startOfDay();
            $toDate = Carbon::createFromFormat('d M, Y', $toDate)->endOfDay();
        }

        // Initialize an array to hold the results
        $result = [];

        // Loop through each user to fetch their orders
        foreach ($users as $user) {
            $query = DB::table('orders')
                ->join('order_item', 'orders.order_id', '=', 'order_item.order_id')
                ->select(
                    DB::raw('CAST(orders.order_id AS UNSIGNED) AS order_id'),
                    'orders.order_date',
                    DB::raw("'" . $user->username . " (" . $user->user_role . ")' AS user_name"),
                    'orders.grand_total',
                    DB::raw('SUM(CASE WHEN order_item.winning_amount != 0 THEN order_item.winning_amount ELSE 0 END) AS winning_amount')
                )
                ->where('orders.user_id', $user->user_id)
                ->whereBetween('orders.order_date', [$fromDate, $toDate])
                ->groupBy('orders.order_id', 'orders.order_date', 'user_name', 'orders.grand_total');

            // Execute the query to get the orders for the current user
            $orders = $query->get();

            foreach ($orders as $order) {
                $result[] = [
                    'order_id' => (int) $order->order_id,
                    'nine_order_id' => str_pad((int) $order->order_id, 9, '0', STR_PAD_LEFT),
                    'order_date' => $order->order_date,
                    'user_name' => $order->user_name,
                    'grand_total' => $order->grand_total,
                    'winning_amount' => $order->winning_amount
                ];
            }
        }

        if (empty($result)) {
            return response()->json([
                'msg' => 'No orders found',
                'success' => false,
                'orders' => $result
            ]);
        } else {
            return response()->json([
                'msg' => 'Order history fetched successfully',
                'success' => true,
                'orders' => $result
            ]);
        }
    } catch (\Exception $e) {
        return response()->json([
            'msg' => 'Error fetching order history',
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}


    // public function createOrder(Request $request)
    // {
    //     $url = 'abvcd';
    //     $today = now()->toDateString();
    //     $data = $request->input('data');

    //     // Check if the user is authenticated
    //     if (auth()->check()) {
    //         $user = auth()->user();
    //         $userId = $user->user_id;

    //         if (!empty($data)) {
    //             // Rest of your code...

    //             $order = new Order([
    //                 'order_date' => $today,
    //                 'client_name' => $request->input('name'),
    //                 'client_contact' => $request->input('number'),
    //                 'user_id' => $userId,
    //                 'sub_total' => 0,
    //             ]);

    //             // Rest of your code...

    //             $response = [
    //                 'url' => $url,

    //                 'msg' => 'Lottery Sold Successfully',
    //                 'is_Status' => 1,
    //             ];
    //         } else {
    //             $response = [
    //                 'url' => '',
    //                 'orderID' => 0,
    //                 'msg' => 'Error: No data provided.',
    //                 'is_Status' => 0,
    //             ];
    //         }
    //     } else {
    //         $response = [
    //             'url' => '',
    //             'orderID' => 0,
    //             'msg' => 'Error: User not authenticated.',
    //             'is_Status' => 0,
    //         ];
    //     }

    //     return response()->json($response);
    // }

    public function createOrder(Request $request)
{
    $today = now()->toDateString();
    $data = $request->input('data');

    if (auth()->check()) {
        $user = auth()->user();
        $userId = $user->user_id;

        if (!empty($data)) {
            // Create Order without saving
            $order = new Order([
                'order_date' => $today,
                'client_name' => $request->input('name'),
                'client_contact' => $request->input('number'),
                'user_id' => $userId,
                'sub_total' => 0,
            ]);

            //dd( $order);

            $order->save(); // Save Order

            $currentOrderId = $order->order_id;

            $grandTotal = 0;

            foreach ($data as $item) {
                $calculated = $item['frac'];
                // Create OrderItem without saving
                $orderItem = new OrderItem([
                    'order_id' => $currentOrderId,
                    'product_id' => $item['loteryId'],
                    'product_name' => $item['loteryName'],
                    'lot_number' => $item['number'],
                    'lot_frac' => $calculated,
                    'lot_amount' => $item['quator'],
                    'lot_type' => $item['type'],
                ]);

                $orderItem->save(); // Save OrderItem

                if (is_numeric($item['quator'])) {
                    $grandTotal += $item['quator'];
                } else {
                    $grandTotal += 0;
                }
            }

            $transaction = Transaction::create([
                'debit' => 0,
                'credit' => $grandTotal,
                'balance' => 0,
                'seller_id' => $userId,
                'transaction_remarks' => 'Lottery sold.' . $currentOrderId,
            ]);

            $orderId = $currentOrderId;

            Order::where('order_id', $orderId)->update([
                'sub_total' => $grandTotal,
                'grand_total' => $grandTotal,
                'transaction_id' => $transaction->transaction_id,
            ]);
            // dd($currentOrderId);
            //get order
            $orderDetails = $this->getOrderDetails($currentOrderId);


            $response = [
                'success' => true,
                'msg' => 'Lottery Added Successfully',
                'orderID' => $orderId,
                'lotteryData' => $orderDetails,
            ];
        } else {
            $response = [
                'success' => false,
                'msg' => 'Error',
                'orderID' => '',
                'lotteryData' => '',
            ];
        }
    } else {
        $response = [

            'success' => false,
            'msg' => 'Error: User not authenticated.',
            'orderID' => '',
            'lotteryData' => '',
        ];
    }

    return response()->json($response);
}



public function deleteOrder(Request $request, $id)
{


    // Retrieve order_id from the request
    $order_id = $id;

    // Delete related records
    try {
        OrderItem::where('order_id', $order_id)->delete();
        $order = Order::find($order_id);
        if ($order) {
            Transaction::where('transaction_id', $order->transaction_id)->delete();
            $order->delete();
        }
    } catch (\Exception $e) {
        // If an error occurs during deletion, return an error response
        return response()->json([
            'success' => false,
            'msg' => 'Error: User not authenticated.',
            'error' => 'Failed to delete order'], 500);
    }

    // Return a success message
    return response()->json([
        'success' => true,

        'msg' => 'Order and related records deleted successfully']);
}





public function orderList(Request $request){
    // Retrieve orders from the database
    $user_id = auth()->user();
    // Get the current time
$currentDateTime = Carbon::now();

$orders = Order::select(
    'order_id', DB::raw("LPAD(orders.order_id, 9, '0') AS nine_order_id"), 'order_date', 'client_name', 'client_contact',
    'sub_total', 'grand_total',
    \DB::raw("CASE WHEN TIMESTAMPDIFF(MINUTE, adddatetime, '$currentDateTime') <= 10 THEN 1 ELSE 0 END as is_deleted")
)
->with(['orderItems' => function ($query) {
    $query->select('order_id', 'product_id', 'product_name', 'lot_number', 'lot_frac', 'lot_amount');
}])
->where('user_id', $user_id->user_id)
->orderBy('orders.order_id', 'DESC')
->limit(100)
->get();





    // You can then pass the $orders variable to your view or process it further as needed

    // For example, if you want to return JSON response
    return response()->json([
        'success' => true,
        'msg' => 'order get ',
        'orders' => $orders]);
}


public function printOrder(Request $request, $id, $orderItem = null)
{
    // Check if the id is provided in the URL
        if($orderItem !== null && $orderItem != 'web') {
        
        // Retrieve the order item based on order_item_id using DB facade
            $orderItemData = DB::table('order_item')->where('order_id', $id)->where('order_item_id', $orderItem)->first();
            
            if (!$orderItemData) {
                return response()->json(['success' => false, 'msg' => 'Order item not found'], 200);
            }
            
            // Update the verify_status of the order item
            DB::table('order_item')
                ->where('order_item_id', $orderItem)
                ->update(['verify_status' => 'verified']);
        
        return response()->json(['success' => true, 'msg' => 'Order item verified'], 200);
        
        }else{
            if($orderItem !== null && $orderItem == 'web'){
                
                // Regular print order logic when id is present
        $currentOrderId = $id;

        // Assuming $orderDetails contains the details of the current order
        $orderDetails = $this->getOrderDetails($currentOrderId);

        // Generate QR Code
        $url = "https://deltholotto.thewebconcept.com/api/printOrder/" . $currentOrderId;
        $qrCode = Builder::create()
            ->writer(new PngWriter())
            ->data($url)
            ->size(300)
            ->margin(10)
            ->build();

        $qrCodeDataUri = $qrCode->getDataUri();

        // Construct the response
        $response = [
            'success' => true,
            'msg' => 'Lottery get Successfully',
            'orderID' => $currentOrderId,
            'lotteryData' => $orderDetails,
            'qrCode' => $qrCodeDataUri,
        ];

        // Return the response
        return view('print', ['data' => $response]);
                
            }else{
                                // Regular print order logic when id is present
        $currentOrderId = $id;

        $orderDetails = Order::select('order_id', DB::raw("LPAD(orders.order_id, 9, '0') AS nine_order_id"), 'order_date', 'client_name', 'client_contact', 'sub_total', 'grand_total')
    ->with(['orderItems' => function ($query) {
        $query->select('order_item_id', 'order_id', 'product_id', 'product_name', 'lot_number', 'lot_frac', 'lot_amount', 'lot_type', 'winning_amount');
    }])
    ->where('order_id', $currentOrderId)
    ->first();

    if ($orderDetails) {
        
        $groupedOrderItems = [];
        foreach ($orderDetails->orderItems as $orderItem) {
            $lotteryId = $orderItem->product_id;
            
            // Check if the lottery ID already exists in the groupedOrderItems array
             
            if (!isset($groupedOrderItems[$lotteryId])) {
                $groupedOrderItems[$lotteryId] = [];
            }

            // Add the current order item details to the corresponding lottery ID array
            $groupedOrderItems[$lotteryId][] = [
                'lot_number' => $orderItem->lot_number,
                'lot_frac' => $orderItem->lot_frac,
                'lot_amount' => $orderItem->lot_amount
            ];
        }
        //$orderDetails->groupedOrderItems = $groupedOrderItems;
    }

        // Generate QR Code
        $url = "https://deltholotto.thewebconcept.com/api/printOrder/" . $currentOrderId;
        $qrCode = Builder::create()
            ->writer(new PngWriter())
            ->data($url)
            ->size(300)
            ->margin(10)
            ->build();

        $qrCodeDataUri = $qrCode->getDataUri();

        // Construct the response
        $response = [
            'success' => true,
            'msg' => 'Lottery get Successfully',
            'win_msg' => 'The colored lotteries are won!',
            'orderID' => $currentOrderId,
            'lotteryData' => $orderDetails,
            'qrCode' => $qrCodeDataUri,
        ];

        // Return the response
        return view('print', ['data' => $response]);
        
            }
            
        }
            
}



public function orderprint(Request $request , $id)
{
    // Assuming $currentOrderId is available
    $currentOrderId = $id; // Replace with your actual logic to get the current order ID

    // Assuming $orderId is available and contains the ID of the current order
    $orderId = $currentOrderId; // Replace with your actual logic to get the order ID

    // Assuming $orderDetails is available and contains the details of the current order
    // Replace this line with your logic to get order details
    $orderDetails = $this->getOrderDetails($currentOrderId);

    // Construct the response
    $response = [
        'success' => true,
        'msg' => 'Lottery get Successfully',
        'orderID' => $orderId,
        'lotteryData' => $orderDetails,
    ];

    // Return the response
    return response()->json($response);
}




function getOrderDetails($orderId) {
    $orderDetails = Order::select('order_id', DB::raw("LPAD(orders.order_id, 9, '0') AS nine_order_id"), 'order_date', 'client_name', 'client_contact', 'sub_total', 'grand_total')
        ->with(['orderItems' => function ($query) {
            $query->select('order_item_id', 'order_id', 'product_id', 'product_name', 'lot_number', 'lot_frac', 'lot_amount', 'lot_type');
        }])
        ->where('order_id', $orderId)
        ->first();

    if ($orderDetails) {
        
        $groupedOrderItems = [];
        foreach ($orderDetails->orderItems as $orderItem) {
            $lotteryId = $orderItem->product_id;
            
            // Check if the lottery ID already exists in the groupedOrderItems array
             
            if (!isset($groupedOrderItems[$lotteryId])) {
                $groupedOrderItems[$lotteryId] = [];
            }

            // Add the current order item details to the corresponding lottery ID array
            $groupedOrderItems[$lotteryId][] = [
                'lot_number' => $orderItem->lot_number,
                'lot_frac' => $orderItem->lot_frac,
                'lot_amount' => $orderItem->lot_amount
            ];
        }
        //$orderDetails->groupedOrderItems = $groupedOrderItems;
    }

    return $orderDetails;
}

}
