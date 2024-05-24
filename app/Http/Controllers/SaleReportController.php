<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Lottery;
use App\Models\User;
use Carbon\Carbon;

class SaleReportController extends Controller
{
    public function saleReport(Request $request)
    {
        $lotId = $request->input('lottery');

        $userId = auth()->user()->user_id;
        $user = auth()->user();

       $fromDate = $request->input('fromdate');
$toDate = $request->input('todate');

// Parse the input dates using Carbon
$fromDateCarbon = Carbon::createFromFormat('j M, Y - H:i', $fromDate);
$toDateCarbon = Carbon::createFromFormat('j M, Y - H:i', $toDate);

// Get the date portion (YYYY-MM-DD)
$fromDate = $fromDateCarbon->format('Y-m-d');
$toDate = $toDateCarbon->format('Y-m-d');


        $lottery = Lottery::find($lotId);
        //$user = User::find($userId);
        //dd($lotId);
        if (!$lottery || !$user) {
            return response()->json(['error' => 'Invalid lottery or user.'], 404);
        }

        $salesData = [];

        for ($i = 0; $i <= 99; $i++) {
            $numberN = sprintf("%02d", $i);
            $salesData['numberlist'][$numberN]=$this->getAmount($userId, $lotId, $numberN, $fromDate, $toDate);
        }

         $salesData['totalSold'] = $this->getTotalSold($userId, $lotId, $fromDate, $toDate);
        $salesData['commission'] = $this->getCommission($userId, $lotId, $fromDate, $toDate, $user->commission);
        $salesData['winnings'] = $this->getWinnings($userId, $lotId, $fromDate, $toDate);
        $salesData['balance'] = $this->getBalance($userId, $lotId, $fromDate, $toDate,$user->commission);
        $salesData['winningNumbersTotal'] = $this->getWinningNumbersTotal($userId, $lotId, $fromDate, $toDate);

        $salesData['lotteryName'] = $lottery->lot_name;
        $salesData['date'] = now()->format('d-m-Y h:i');

        $jsonResponse = [
            'success' => true,
            'msg' => 'Get Successfully',
            'data' => $salesData
        ];


        return response()->json($jsonResponse, 200);
    }

    private function getAmount($userId, $lotId, $numberN, $fromDate, $toDate)
    {
        $total = 0;
        $ordersList = Order::where('user_id', $userId)
            ->whereBetween('order_date', [$fromDate, $toDate])
            ->pluck('order_id')
            ->toArray();

      

        $totalSold = OrderItem::where('product_id', $lotId)
            ->whereIn('order_id', $ordersList)
            ->where('lot_number', $numberN)
            ->sum('lot_amount');

        return number_format($totalSold * 20);
    }

    private function getTotalSold($userId, $lotId, $fromDate, $toDate)
    {
        $ordersList = Order::where('user_id', $userId)
            ->whereBetween('order_date', [$fromDate, $toDate])
            ->pluck('order_id')
            ->toArray();

        $totalSold = OrderItem::where('product_id', $lotId)
            ->whereIn('order_id', $ordersList)
            ->sum('lot_amount');

        return $totalSold * 20;
    }

    private function getCommission($userId, $lotId, $fromDate, $toDate, $commission)
    {
        $totalSold = $this->getTotalSold($userId, $lotId, $fromDate, $toDate);

        return number_format(($totalSold / 100) * $commission, 2);
    }

    private function getWinnings($userId, $lotId, $fromDate, $toDate)
    {
        $ordersList = Order::where('user_id', $userId)
            ->whereBetween('order_date', [$fromDate, $toDate])
            ->pluck('order_id')
            ->toArray();

        $winnings = OrderItem::whereIn('order_id', $ordersList)
            ->where('product_id', $lotId)
            ->whereDate('adddatetime', '>=', $fromDate)
            ->whereDate('adddatetime', '<=', $toDate)
            ->sum('winning_amount');

        return $winnings;
    }

    private function getBalance($userId, $lotId, $fromDate, $toDate,$commission)
    {
        $totalSold = $this->getTotalSold($userId, $lotId, $fromDate, $toDate);
        $commission = $this->getCommission($userId, $lotId, $fromDate, $toDate, $commission);
        $winnings = $this->getWinnings($userId, $lotId, $fromDate, $toDate);

      $totalSold = floatval($totalSold); // Convert to float
$commission = floatval($commission); // Convert to float
$winnings = floatval($winnings); // Convert to float

if (is_numeric($totalSold) && is_numeric($commission) && is_numeric($winnings)) {
    // Perform calculations here
    $result = number_format(($totalSold - $commission - $winnings), 2);
     return $result;
} else {
    return  "0";
}

    }

    private function getWinningNumbersTotal($userId, $lotId, $fromDate, $toDate)
    {
        $ordersList = Order::where('user_id', $userId)
            ->whereBetween('order_date', [$fromDate, $toDate])
            ->pluck('order_id')
            ->toArray();

        $winningNumbersTotal = OrderItem::whereIn('order_id', $ordersList)
            ->where('product_id', $lotId)
            ->whereDate('adddatetime', '>=', $fromDate)
            ->whereDate('adddatetime', '<=', $toDate)
            ->sum('winning_amount');

        return $winningNumbersTotal;
    }
}
