<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;

use Illuminate\Http\Request;

class WinnigController extends Controller
{
    public function addWinningNumber(Request $request)
{
    if (!empty($request->lot_id)) {
        $date = date('y-m-d');
        $lot  = $request->lot_id;
        $win  = $request->win_number;
        $firstWin = $request->first_win_number;
        $secondWin = $request->second_win_number;
        $thirdWin = $request->third_win_number;
        $user = auth()->user();
        $user_id = $user->user_id;
        $customMulNumber = $request->input('multiply_number');

        $winNumbers = [$firstWin, $secondWin, $thirdWin];
        
        DB::beginTransaction();

        try {
            $inserted = DB::table('winning_numbers')->insert([
                'add_date' => $date,
                'lot_id' => $lot,
                'number_win' => $win,
                'first_win_number' => $firstWin,
                'second_win_number' => $secondWin,
                'third_win_number' => $thirdWin,
                'added_by' => $user_id
            ]);

            if ($inserted) {
                $totalwinadded = 0;
                
                // Extract the last two digits of the first winning number
                $lastTwoDigitsOfFirstWin = substr($firstWin, -2);
                
                array_push($winNumbers, $lastTwoDigitsOfFirstWin);
                $winAmount = 0;
                // Find BOR order items
                $getwinorder = DB::table('order_item')
                    ->select('order_item_id', 'order_id', 'product_id', 'product_name', 'lot_number', 'lot_frac', 'lot_amount', 'lottery_gone', 'transaction_paid_id', 'order_item_status', 'winning_amount', 'lotterycollected', DB::raw('cast(adddatetime as date)'))
                    ->where('lottery_gone', '0')
                    ->where('product_id', $lot)
                    ->whereIn('lot_number', $winNumbers)
                    ->where(DB::raw('cast(adddatetime as date)'), $date)
                    ->where('lot_type', 'BOR')
                    ->get();
                
                $lotDetails = DB::table('lotteries')->where('lot_id', $lot)->first();
                
                foreach ($getwinorder as $rowq) {
                    if ($lastTwoDigitsOfFirstWin == $rowq->lot_number) {
                        if($customMulNumber != null){
                        $winAmount = $rowq->lot_amount * $customMulNumber;
                        }else{
                            $winAmount = $rowq->lot_amount * 50;
                        }
                    } elseif ($secondWin == $rowq->lot_number) {
                        $winAmount = $rowq->lot_amount * 20;
                    } elseif ($thirdWin == $rowq->lot_number) {
                        $winAmount = $rowq->lot_amount * 10;
                    }
                    $totalwinadded += $winAmount;

                    DB::table('order_item')
                        ->where('order_item_id', $rowq->order_item_id)
                        ->update(['winning_amount' => $winAmount]);

                    $GetOrder = DB::table('order_item')->where('order_item_id', $rowq->order_item_id)->value('order_id');
                    $GetOrderitemid = DB::table('order_item')->where('order_item_id', $rowq->order_item_id)->value('order_item_id');
                    $user_idNow = DB::table('orders')->where('order_id', $GetOrder)->value('user_id');
                }

                $getMARorder = DB::table('order_item')
                    ->select('order_item_id', 'order_id', 'product_id', 'product_name', 'lot_number', 'lot_frac', 'lot_amount', 'lottery_gone', 'transaction_paid_id', 'order_item_status', 'winning_amount', 'lotterycollected', DB::raw('cast(adddatetime as date)'))
                    ->where('lottery_gone', '0')
                    ->where('product_id', $lot)
                    ->where(DB::raw('cast(adddatetime as date)'), $date)
                    ->where('lot_type', 'MAR')
                    ->get();
                    
                $MarWinAmount = 0;
                
                // Concatenate the required combinations
                $combined1 = $lastTwoDigitsOfFirstWin . 'x' . $secondWin;
                $combined2 = $lastTwoDigitsOfFirstWin . 'x' . $thirdWin;
                $combined3 = $secondWin . 'x' . $thirdWin;
                
                foreach ($getMARorder as $rowq) {
                    // $lotNumbers = explode('x', $rowq->lot_number);
                    // foreach ($lotNumbers as $lotNum) {
                        
                //  dd($lotNum);
                            // if($firstWin == $lotNum){
                            //     $MarWinAmount = $rowq->lot_amount * 50;
                            // }elseif ($secondWin == $lotNum) {
                            //     $MarWinAmount = $rowq->lot_amount * 20;
                            // } elseif ($thirdWin == $lotNum) {
                            //     $MarWinAmount = $rowq->lot_amount * 10;
                            // }
                            
                            if($combined1 == $rowq->lot_number){
                                $MarWinAmount = $rowq->lot_amount * 1000;
                            }elseif ($combined2 == $rowq->lot_number) {
                                $MarWinAmount = $rowq->lot_amount * 1000;
                            } elseif ($combined3 == $rowq->lot_number) {
                                $MarWinAmount = $rowq->lot_amount * 1000;
                            }
                        
                        DB::table('order_item')
                        ->where('order_item_id', $rowq->order_item_id)
                        ->update(['winning_amount' => $MarWinAmount]);
                        
                    // }
                }
                
                // Find LOT3 order items
                $getLOT3order = DB::table('order_item')
                    ->select('order_item_id', 'order_id', 'product_id', 'product_name', 'lot_number', 'lot_frac', 'lot_amount', 'lottery_gone', 'transaction_paid_id', 'order_item_status', 'winning_amount', 'lotterycollected', DB::raw('cast(adddatetime as date)'))
                    ->where('lottery_gone', '0')
                    ->where('product_id', $lot)
                    ->whereIn('lot_number', $winNumbers)
                    ->where(DB::raw('cast(adddatetime as date)'), $date)
                    ->where('lot_type', 'LOT3')
                    ->get();
                $LOT3WinAmount = 0;
                foreach ($getLOT3order as $rowq) {
                        if ($firstWin == $rowq->lot_number) {
                            $LOT3WinAmount = $rowq->lot_amount * 500;
                        }
                        
                        DB::table('order_item')
                            ->where('order_item_id', $rowq->order_item_id)
                            ->update(['winning_amount' => $LOT3WinAmount]);
                }
                
                // Find LOT4 order items
                $LOT4WinAmount = 0;
                $secondThirdWin = $secondWin . $thirdWin;
                $getLOT4order = DB::table('order_item')
                    ->select('order_item_id', 'order_id', 'product_id', 'product_name', 'lot_number', 'lot_frac', 'lot_amount', 'lottery_gone', 'transaction_paid_id', 'order_item_status', 'winning_amount', 'lotterycollected', DB::raw('cast(adddatetime as date)'))
                    ->where('lottery_gone', '0')
                    ->where('product_id', $lot)
                    ->where('lot_number', $secondThirdWin)
                    ->where(DB::raw('cast(adddatetime as date)'), $date)
                    ->where('lot_type', 'LOT4')
                    ->get();
                    
                foreach ($getLOT4order as $rowq) {
                        if ($secondThirdWin == $rowq->lot_number) {
                            $LOT4WinAmount = $rowq->lot_amount * 5000;
                        }
                        
                        DB::table('order_item')
                            ->where('order_item_id', $rowq->order_item_id)
                            ->update(['winning_amount' => $LOT4WinAmount]);
                }
                
                // Find LOT5 order items
                $LOT5WinAmount = 0;
                $firstSecondWin = $firstWin . $secondWin;
                $getLOT5order = DB::table('order_item')
                    ->select('order_item_id', 'order_id', 'product_id', 'product_name', 'lot_number', 'lot_frac', 'lot_amount', 'lottery_gone', 'transaction_paid_id', 'order_item_status', 'winning_amount', 'lotterycollected', DB::raw('cast(adddatetime as date)'))
                    ->where('lottery_gone', '0')
                    ->where('product_id', $lot)
                    ->where('lot_number', $firstSecondWin)
                    ->where(DB::raw('cast(adddatetime as date)'), $date)
                    ->where('lot_type', 'LOT5')
                    ->get();
                    
                foreach ($getLOT5order as $rowq) {
                        if ($firstSecondWin == $rowq->lot_number) {
                            $LOT5WinAmount = $rowq->lot_amount * 25000;
                        }
                        
                        DB::table('order_item')
                            ->where('order_item_id', $rowq->order_item_id)
                            ->update(['winning_amount' => $LOT5WinAmount]);
                }

                $getgoneorder = DB::table('order_item')
                    ->where('lottery_gone', '0')
                    ->where('product_id', $lot)
                    ->get();

                foreach ($getgoneorder as $goneupdate) {
                    DB::table('order_item')
                        ->where('order_item_id', $goneupdate->order_item_id)
                        ->update(['lottery_gone' => '1']);
                }

                // Add your transaction insertion here

                DB::commit();

                $arr = [
                    'msg' => 'Winning Number Added..!',
                    'success' => true,
                ];
            } else {
                $arr = [
                    'msg' => 'Failed to add winning number.',
                    'success' => false,
                ];
            }
        } catch (\Exception $e) {
            DB::rollback();
            $arr = [
                'msg' => $e->getMessage(),
                'success' => false,
            ];
        }

        return response()->json($arr);
    } else {
        return response()->json([
            'msg' => 'Missing required parameters.',
            'success' => false,
        ]);
    }
}



    public function winListAll(Request $request)
    {

            $user = auth()->user()->user_id;

            $getuserde = DB::table('users')->where('user_id', $user)->first();

            if ($getuserde->user_role == 'admin') {
                $thisadmin = $getuserde->user_id;
            } else {
                $getuser1 = DB::table('users')->where('user_id', $getuserde->added_user_id)->first();

                if ($getuser1->user_role == 'admin') {
                    $thisadmin = $getuser1->user_id;
                } else {
                    $getuser3 = DB::table('users')->where('user_id', $getuserde->added_user_id)->first();
                    $getuser31 = DB::table('users')->where('user_id', $getuser3->added_user_id)->first();
                    $thisadmin = $getuser31->user_id;
                }
            }

            $winningList = DB::table('winning_numbers')
                ->select(
                    DB::raw("DATE_FORMAT(winning_numbers.add_date, '%d-%m-%Y') AS add_date"),
                    'lotteries.lot_name',
                    'winning_numbers.number_win',
                    'users.username',
                    'lotteries.lot_colorcode'
                )
                ->join('lotteries', 'winning_numbers.lot_id', '=', 'lotteries.lot_id')
                ->join('users', 'users.user_id', '=', 'winning_numbers.added_by')
                ->where('lotteries.user_added_id', $thisadmin)
                ->orderBy('winning_numbers.win_id', 'DESC')
                ->limit(50)
                ->get();



            return response()->json([
                'msg' => 'Seller-specific action performed',
                'success' => true,
                'data' => $winningList

            ]);

    }





    public function getWinningOrders(Request $request){

        $user = auth()->user();
        //dd($user->user_role);
        switch ($user->user_role) {
            case 'seller':
                return $this->sellerWinningList($user);
                break;
            case 'manager':
                return $this->sellerWinningList($user);
                break;
            case 'admin':
                return $this->adminWinningList($user);
                break;

            default:
                return response()->json(['error' => 'User Role not defined']);
        }




    }



    protected function sellerWinningList($user)
{
    // Implement seller-specific logic here
    $userId = $user->user_id;
    $user = DB::table('users')->where('user_id', $userId)->first();

    if ($user) {
        $adminUserId = $this->getAdminUserId($user);
        //dd($adminUserId);
        $lotteries = DB::table('lotteries')->where('user_added_id', $adminUserId)->pluck('lot_id')->toArray();
        //$lotIds = implode(',', $lotteries);
        //dd($lotIds);
        $query = DB::table('order_item')
        ->select(
            'lotteries.lot_name',
            DB::raw("LPAD(orders.order_id, 9, '0') AS ticket_id"),
            'orders.client_name',
            'orders.client_contact',
            'order_item.lot_number',
            'order_item.winning_amount',
            'order_item.order_item_id',
            'order_item.lot_type',
            'order_item.verify_status',
            DB::raw("DATE_FORMAT(orders.adddatetime, '%d-%m-%Y %H:%i:%s') AS adddatetime"),
            'users.username AS sellername',
            'users.added_user_id as useraddedID',
            'lotteries.lot_colorcode',
            'm.username AS managername',
            DB::raw("CASE WHEN order_item.transaction_paid_id IS NULL THEN '0' ELSE 1 END AS paidthis")
        )
        ->join('orders', 'order_item.order_id', '=', 'orders.order_id')
        ->join('lotteries', 'lotteries.lot_id', '=', 'order_item.product_id')
        ->join('users', 'users.user_id', '=', 'orders.user_id')
        ->join('users as m', 'm.user_id', '=', 'users.added_user_id') // Ensure $userId is properly used
        ->where('order_item.lottery_gone', 1)
        ->where('order_item.winning_amount', '>', 0)
        ->where('orders.user_id', '=', $userId)
        ->whereIn('lotteries.lot_id', $lotteries)
        ->orderBy('orders.order_id', 'DESC');

    $sql = $query->get();

if ($sql->isEmpty()) {
            return response()->json([
                'msg' => 'No data found for this seller',
                'success' => false,
                'data' => []
            ]);
        }

    return response()->json([
        'msg' => 'Seller-specific action performed',
        'success' => true,
        'data' => $sql

    ]);



    }


    return response()->json(['message' => 'Seller-specific action performed']);
}

protected function managerWinningList($user)
{
    // Implement manager-specific logic here
    return response()->json(['message' => 'Manager-specific action performed']);
}

protected function adminWinningList($user)
{
    $adminUserId = $user->user_id;
    //dd($adminUserId);
    $query = DB::table('order_item')
    ->select(
        'lotteries.lot_name',
        DB::raw("LPAD(orders.order_id, 9, '0') AS ticket_id"),
        'orders.client_name',
        'orders.client_contact',
        'order_item.lot_number',
        'order_item.winning_amount',
        'order_item.order_item_id',
        'order_item.lot_type',
        'order_item.verify_status',
        DB::raw("DATE_FORMAT(orders.adddatetime, '%d-%m-%Y %H:%i:%s') AS adddatetime"),
        'users.username AS sellername',
        'users.added_user_id as useraddedID',
        'lotteries.lot_colorcode',
        'm.username AS managername',
        DB::raw("CASE WHEN order_item.transaction_paid_id IS NULL THEN '0' ELSE 1 END AS paidthis")
    )
    ->join('orders', 'order_item.order_id', '=', 'orders.order_id')
    ->join('lotteries', 'lotteries.lot_id', '=', 'order_item.product_id')
    ->join('users', 'users.user_id', '=', 'orders.user_id')
    ->join('users as m', 'm.user_id', '=', 'users.added_user_id')
    ->where('order_item.lottery_gone', 1)
    ->where('order_item.winning_amount', '>', 0)
    ->where('lotteries.user_added_id', $adminUserId)
    ->orderBy('orders.order_id', 'DESC');




    $sql = $query->get();

if ($sql->isEmpty()) {
            return response()->json([
                'msg' => 'No data found for this admin',
                'success' => false,
                'data' => []
            ]);
        }

    return response()->json([
        'msg' => 'admin-specific action performed',
        'success' => true,
        'data' => $sql

    ]);


}


private function getAdminUserId($user)
    {
        if ($user->user_role == 'admin') {
            return $user->user_id;
        }

        $addedUser = DB::table('users')->where('user_id', $user->added_user_id)->first();

        if ($addedUser->user_role == 'admin') {
            return $addedUser->user_id;
        }

        $addedUser2 = DB::table('users')->where('user_id', $addedUser->added_user_id)->first();
        return $addedUser2->user_id;
    }



}
