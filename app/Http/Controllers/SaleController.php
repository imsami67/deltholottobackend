<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class SaleController extends Controller
{
    
    public function loanList(Request $request) {
    try {
        $sellerId = $request->input('seller_id');

        // Ensure seller_id is provided
        if (!$sellerId) {
            return response()->json(['success' => false, 'message' => 'Seller ID is required'], 400);
        }

        // Build the query to get loans for the specific seller
        $loanReport = DB::table('loans')
            ->where('loans.seller_id', $sellerId)
            ->leftJoin('users as added_user', 'loans.added_user_id', '=', 'added_user.user_id')
            ->leftJoin('users as seller_user', 'loans.seller_id', '=', 'seller_user.user_id')
            ->select('loans.*', 'added_user.username as added_username', 'seller_user.username as sellername')
            ->get();

        if ($loanReport->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No loan report found for this seller', 'data' => [], 'total_balance' => 0], 200);
        }

        // Initialize total balance and running total
        $totalBalance = 0;

        // Calculate cumulative balance for each transaction
        $loanReport = $loanReport->map(function($loan) use (&$totalBalance) {
            // Cast credit and debit to float or int
            $credit = (float)$loan->credit;  // or (int)$loan->credit if you prefer integers
            $debit = (float)$loan->debit;    // or (int)$loan->debit if you prefer integers
            
            // Update the running total balance
            $totalBalance += $credit;   // Add credit to the total balance
            $totalBalance -= $debit;    // Subtract debit from the total balance

            // Set the cumulative balance for this transaction
            $loan->balance = $totalBalance;

            return $loan;
        });

        // Include total balance in the response
        return response()->json([
            'success' => true, 
            'data' => $loanReport, 
            'total_balance' => $totalBalance
        ], 200);

    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
}





public function addLoan(Request $request)
{
    try {
        DB::beginTransaction();
        $user = Auth()->user();
        
        $sellerId = $request->input('seller_id');
        $creditAmount = $request->input('credit_amount');
        $debitAmount = $request->input('debit_amount');
        $transactionRemarks = $request->input('transaction_remarks');
        
        if($user->user_role == 'admin' || $user->user_role == 'manager') {
            $loanData = [
                'added_user_id' => $user->user_id,
                'seller_id' => $sellerId,
                'transaction_remarks' => $transactionRemarks,
            ];
            
            if (!empty($creditAmount)) {
                $loanData['credit'] = $creditAmount;
                $message = 'Loan added successfully';
                
                $notification = DB::table('notifications')->insert([
                    'added_user_id' => $user->user_id,
                    'seller_id' => $sellerId,
                    'notification_message' => 'The amount '. $creditAmount .' has been credited to your account'
                    ]);
                
            } elseif (!empty($debitAmount)) {
                $loanData['debit'] = $debitAmount;
                $message = 'Loan collected successfully';
                
                $notification = DB::table('notifications')->insert([
                    'added_user_id' => $user->user_id,
                    'seller_id' => $sellerId,
                    'notification_message' => 'The amount '. $debitAmount .' has been debited on your account'
                    ]);
                
            } else {
                return response()->json(['success' => false, 'message' => 'Either credit or debit amount must be provided'], 400);
            }
            
            DB::table('loans')->insert($loanData);
            
            DB::commit();
            
            return response()->json(['success' => true, 'message' => $message], 200);
        } else {
            return response()->json(['success' => false, 'message' => 'User not authorized'], 400);
        }
        
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
}


public function addVoucher(Request $request)
{
    try{
    DB::beginTransaction();
     $user = Auth()->user();
    
    $userId = $request->input('user_id');
    $amount = $request->input('amount');
    $note = $request->input('note');
    
    DB::table('vouchers')->insert([
        'transaction_id' => 0,
        'user_id' => $userId,
        'added_user_id' => $user->user_id,
        'givin_amount' => $amount,
        'voucher_hint' => $note,
    ]);
    DB::commit();
    
    return response()->json(['success' => true, 'message' => 'Voucher added successfully'], 200);
    
    }catch(\Exception $e){
    DB::rollBack();
    return response()->json(['success' => false, 'message' => $e->getMessage()], 200);    
    }
}

public function voucherList(Request $request)
{
    try {
        $user = Auth()->user();
        
        if ($user->user_role == 'admin') {
            $vouchers = DB::table('vouchers')
                ->join('users', 'vouchers.user_id', '=', 'users.user_id')
                ->where('vouchers.added_user_id', $user->user_id)
                ->select('vouchers.*', 'users.*')
                ->get();
        } elseif ($user->user_role == 'manager') {
            // Assuming managers should see all vouchers added by users they manage
            $vouchers = DB::table('vouchers')
                ->join('users', 'vouchers.user_id', '=', 'users.user_id')
                ->where('users.manager_id', $user->user_id)
                ->select('vouchers.*', 'users.*')
                ->get();
        } elseif ($user->user_role == 'seller') {
            // Assuming you meant a different condition here, such as 'superadmin'
            // Modify as needed for the actual role and logic
            $vouchers = DB::table('vouchers')
                ->join('users', 'vouchers.user_id', '=', 'users.user_id')
                ->where('user_id', $user->user_id)
                ->select('vouchers.*', 'users.*')
                ->get();
        }
        
        return response()->json(['success' => true, 'data' => $vouchers], 200);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()], 200);
    }
}

    public function limitlist(Request $request, $user){

        //dd($user);
        $response = [];

        try {
            $queryResult = DB::table('limit_game')
                ->join('lotteries', 'lotteries.lot_id', '=', 'limit_game.lottery_id')
                ->where('limit_game.user_id', $user)
                ->get();
        } catch (\Exception $e) {
            dd($e->getMessage());
        }


    //dd($queryResult);

foreach ($queryResult as $r) {
    $lotteryName = $r->lot_name;
    $lotterycolor = $r->lot_colorcode;
    $limitData = [
        'limit_ball' => $r->limit_ball,
        'limit_frac' => $r->limit_frac,
        'limit_id' => $r->limit_id,
        // Add other limit-related columns here
    ];

    if (!isset($response[$lotteryName])) {
        $response[$lotteryName] = [
            'lottery_color' => $lotterycolor,
            'limits' => [],
        ];
    }

    $response[$lotteryName]['limits'][] = $limitData;
}

$finalResponse = [];
foreach ($response as $lotteryName => $lotteryInfo) {
    $lotteryInfo['lottery_name'] = $lotteryName;
    $finalResponse[] = $lotteryInfo;
}

if (empty($finalResponse)) {
    $re = [
        'data' => [],
        'success' => false ,
        'msg' => 'Nothing Found'
    ];
} else {
    $re = [
        'data' => $finalResponse,
        'success' => true,
        'msg' => 'Get Data'
    ];
}

//echo json_encode($re);


return response()->json($re);

    }


    public function checkLimit(Request $request)
{
    $entityBody = $request->getContent();
    $user = auth()->user();
    
    
   
     

    $obj = json_decode($entityBody);
    $size = count(@$obj->cartDataList);
    date_default_timezone_set("America/Guatemala");
    $servertimewithgutemala = now()->format('H:i:s');

    $testArr = [];
    for ($i = 0; $i < $size; $i++) {
        $lotteryid = ($obj->cartDataList[$i]->loteryId);
        $frac = (int)($obj->cartDataList[$i]->frac);
        $number = ($obj->cartDataList[$i]->number);
        $quator = ($obj->cartDataList[$i]->quator);
        $loteryName = ($obj->cartDataList[$i]->loteryName);
        $colorcode = ($obj->cartDataList[$i]->lotColor);
        //dd($user->user_id);
        $currentDateTime = now()->format('H:i:s');
//               $d = DB::select("SELECT lg.*, l.*
//             FROM lotteries l
//             LEFT JOIN limit_game lg ON l.lot_id = lg.lottery_id AND lg.status = '1'
//             WHERE l.lot_id = '$lotteryid'
//                 AND '$currentDateTime' >= l.lot_opentime
//                 AND '$currentDateTime' <= l.lot_closetime
//                 AND lg.limit_ball = '$number'
//                 AND lg.limit_frac <= '$frac'
//                 AND (lg.user_id = '$user->user_id' OR lg.user_id = '$added_user_id->user_id')
// ");
        
       $d = DB::select("SELECT * FROM limit_game WHERE 
    lottery_id = '$lotteryid'
    AND limit_ball = '$number'
    AND limit_frac >= '$frac'
    AND (user_id = '$user->user_id' OR user_id = '$user->added_user_id')
");

        


        // $lotery = DB::select("SELECT lot_id,lot_name AS name,is_open,multiply_number,img_url,winning_type,lot_opentime,lot_closetime,
        //     CASE
        //         WHEN lot_colorcode = '' THEN 'Color(0xff1cff19)'
        //         WHEN lot_colorcode IS NULL THEN  'Color(0xffEAF8A3)'
        //         ELSE lot_colorcode
        //     END
        //     AS colorcode FROM lotteries WHERE lot_id = '$lotteryid'
        // ");

        // $colorcode = collect($lotery)->first();

        $d1 = collect($d)->first();
        //dd($d1->limit_frac);

        if ($d1) {
            //dd($d1);
            $sts = "false";
        } else {
            $sts = "true";
        }
        //dd($sts);

        $testArr[] = [
            'number' => $number,
            'quator' => $quator,
            'frac'   => "$frac",
            'loteryId' => $lotteryid,
            'limit'   => $sts,
            'loteryName' => $loteryName,
            'lotColor' => $colorcode,
        ];
    }

    $finalArr = [
        'success' => true,
        'msg' => 'Lottery List',
        'cartDataList' => $testArr,

    ];

    return response()->json($finalArr);
}




    public function addLimit(Request $request)
    {
        $user = $request->input('user_id');
        $frac = $request->input('limit_amount');
        $limit_ball = $request->input('limit_number');
        $lotType = $request->input('lot_type');

        $added_user_id = auth()->user()->user_id;

        $lotid = $request->input('lottery_id');


            try {
                DB::beginTransaction();

                    DB::table('limit_game')->insert([
                        'lottery_id' => $lotid,
                        'limit_frac' => $frac,
                        'user_id' => $user,
                        'limit_ball' => $limit_ball,
                        'added_user_id' => $added_user_id,
                        'lot_type' => $lotType,
                    ]);

                DB::commit();

                $response = [
                    'success' => true,
                    'msg' => 'Limit Added successfully',
                ];
            } catch (\Exception $e) {
                DB::rollBack();

                $response = [

                    'success' => false,
                     'msg'    => $e->getMessage(),
                ];
            }


        return response()->json($response);
    }



public function deleteLimitsingle(Request $request, $limitID)
{
    try {
        // Start a database transaction
        DB::beginTransaction();

        // Delete the limit using the DB facade
        DB::table('limit_game')->where('limit_id', $limitID)->delete();

        // Commit the transaction if the deletion is successful
        DB::commit();

        return response()->json([
            'success' => true,
            'msg' => 'Limit Deleted successfully for numbers ',
        ], 200);
    } catch (\Exception $e) {
        // Rollback the transaction if an exception occurs
        DB::rollback();

        // Handle exceptions (e.g., database error)
        return response()->json([
            'success' => false,
            'msg' => 'Failed to delete limit. ' . $e->getMessage()

        ], 500);
    }
}


public function deleteLimitlottery(Request $request)
{
    try {
        // Start a database transaction
        DB::beginTransaction();
        $lotteryID = $request->input('lottery_id');
        $userID = $request->input('user_id');
        // Delete the limit using the DB facade
        DB::table('limit_game')
        ->where('lottery_id', $lotteryID)
        ->where('user_id', $userID)
        ->delete();

        // Commit the transaction if the deletion is successful
        DB::commit();

        return response()->json([
            'success' => true,
            'msg' => 'Limit Deleted successfully ',
        ], 200);
    } catch (\Exception $e) {
        // Rollback the transaction if an exception occurs
        DB::rollback();

        // Handle exceptions (e.g., database error)
        return response()->json([
            'success' => false,
            'msg' => 'Failed to delete limit. ' . $e->getMessage()

        ], 500);
    }
}




}
