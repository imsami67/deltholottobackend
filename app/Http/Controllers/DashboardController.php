<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
     public function dashboard(){
        $user = auth()->user();
        //dd($user->user_role);
        switch ($user->user_role) {
            case 'admin':
                return $this->adminDashboard($user);
                break;
            case 'manager':
                return $this->managerDashboard($user);
                break;
            case 'seller':
                return $this->sellerDashboard($user);
                break;
            case 'superadmin':
                return $this->superAdminDashboard($user);
                break;
            default:
                return response()->json(['error' => 'User Role not defined']);
        }



     }


    public function getdashboard(Request $request, $user_id ){

        //$user = auth()->user();

        $UserID = DB::table('users')
        ->where('user_id', $user_id)

        ->get();
        $user = $UserID[0];

        //dd($user->user_role);
        switch ($user->user_role) {
            case 'admin':
                return $this->adminDashboard($user);
                break;
            case 'manager':
                return $this->managerDashboard($user);
                break;
            case 'seller':
                return $this->sellerDashboard($user);
                break;
            case 'superadmin':
                return $this->superAdminDashboard($user);
                break;
            default:
                return response()->json(['error' => 'User Role not defined']);
        }

    }



     public function SuperAdminDashboard($user)

{

    if ($user->user_role == 'superadmin' ) {
        $date = now();
        $userId = $user->user_id;

        $lastCutHistory = DB::table('cut_history')
            ->where('user_id', $userId)
            ->orderByDesc('cut_id')
            ->first();

            if ($lastCutHistory !== null) {
                $showFromDate = $lastCutHistory->add_datetime;
            }else{
                $showFromDate = date('d-m-y');
            }

        $userDetails = $user->user_role;


            $totalCollected = DB::table('orders')->where('adddatetime', '>', $showFromDate)->sum('grand_total');
            $userCounts = DB::table('users')
            ->selectRaw('
                COUNT(CASE WHEN user_role = "seller" THEN 1 END) as totalSeller,
                COUNT(CASE WHEN user_role = "manager" THEN 1 END) as totalManager,
                COUNT(CASE WHEN user_role = "admin" THEN 1 END) as totalAdmin
            ')
            ->first();


            $totalLot = DB::table('lotteries')->count();

            $totalPaid = DB::table('transactions')
                ->whereNotNull('order_item_id')
                ->where('transaction_add_date', '>', $showFromDate)
                ->sum('debit');

            $totalCashInHand = DB::table('transactions')->sum(DB::raw('(credit - debit)'));




            $data = [
                [
                    'img' => asset('assets/images/2.png'),
                    'name' => 'Total Admin',
                    'spanishName' => 'Administración',
                    'amount' => number_format($userCounts->totalAdmin),
                ],
                [
                    'img' => asset('assets/images/2.png'),
                    'name' => 'Total Manager',
                    'spanishName' => 'Gerente Total',
                    'amount' => number_format($userCounts->totalManager),
                ],
                [
                    'img' => asset('assets/images/2.png'),
                    'name' => 'Total Sellers',
                    'spanishName' => 'Vendedores Totales',
                    'amount' => number_format($userCounts->totalSeller),
                ],
                [
                    'img' => 'https://cdn-icons-png.flaticon.com/512/5525/5525335.png',
                    'name' => 'Total Lotteries',
                    'spanishName' => 'Loterías Totales',
                    'amount' => number_format($totalLot),
                ],
                [
                    'img' => asset('assets/images/2.png'),
                    'name' => 'App',
                    'spanishName' => 'App',
                    'amount' => number_format($totalCollected * 0.005, 2),
                ],
            ];

            $jsonResponse = ['data' => $data ,
            'cutList' =>  [],
            'success' => true,
            'msg'       => 'Get Successfully',
        ];

            return response()->json($jsonResponse);

    }

    return response()->json(['msg' => 'Invalid request' , 'success' => false], 401);
}





//admin dashboard



public function adminDashboard($user)
{
    if ($user->user_role == 'admin' ) {
        $date = now();
        $userId = $user->user_id;
        $user = $user->user_id;

        $managerIds = DB::table('users')
            ->where('added_user_id', $user)
            ->where('user_role', 'manager')
            ->pluck('user_id');

        $sellerIds = DB::table('users')
            ->whereIn('added_user_id', $managerIds)
            ->where('user_role', 'seller')
            ->pluck('user_id');

        $sellerIds = array_merge($sellerIds->toArray(), DB::table('users')
            ->where('added_user_id', $user)
            ->where('user_role', 'seller')
            ->pluck('user_id')->toArray());

        $orderIds = DB::table('orders')
            ->whereIn('user_id', $sellerIds)
            ->where('adddatetime', '>', now())
            ->pluck('order_id');

        $totalSold = DB::table('orders')
            ->whereIn('user_id', $sellerIds)
            ->where('adddatetime', '>', now())
            ->count();

        $totalCollected = DB::table('orders')
            ->whereIn('user_id', $sellerIds)
            ->where('adddatetime', '>', now())
            ->sum('grand_total');

            $totalLot = DB::table('lotteries')
            ->where('user_added_id', $user)
            ->count();


        $totalPaid = DB::table('transactions')
            ->whereIn('seller_id', $sellerIds)
            ->where('transaction_add_date', '>', now())
            ->whereNotNull('order_item_id')
            ->where('balance', '1')
            ->sum('debit');

        $totalWin = DB::table('order_item')
            ->whereIn('order_id', $orderIds)
            ->sum('winning_amount');

        $totalManagerCommission = DB::table('users as mu')
            ->leftJoin('users as su', 'su.added_user_id', '=', 'mu.user_id')
            ->leftJoin('orders as o', 'o.user_id', '=', 'su.user_id')
            ->where('mu.user_role', 'manager')
            ->where('mu.added_user_id', $user)
            ->where('o.adddatetime', '>', now())
            ->groupBy('mu.user_id')
            ->sum(DB::raw('o.grand_total * mu.commission / 100'));

        $totalAppCommission = $totalCollected * 0.005;

        $balance = $totalCollected - $totalWin - $totalManagerCommission;

        $emparray = [
            [
                'img' => asset('assets/images/1.png'),
                'name' => 'Lotteries amount (Sellers)',
                'spanishName' => 'Cantidad de loterías (Vendedores)',
                'amount' => number_format($totalCollected, 2),
            ],
            [
                'img' => asset('assets/images/4.png'),
                'name' => 'Paid winning Number',
                'spanishName' => 'Ganador Pagado',
                'amount' => number_format($totalWin, 2),
            ],
            [
                'img' => asset('assets/images/3.png'),
                'name' => ' Commision',
                'spanishName' => 'comisión ',
                'amount' => number_format($totalManagerCommission, 2),
            ],
            [
                'img' => asset('assets/images/5.png'),
                'name' => 'Balance',
                'spanishName' => 'Saldo',
                'amount' => number_format($balance, 2),
            ],
            [
                'img' => asset('assets/images/2.png'),
                'name' => 'App',
                'spanishName' => 'App',
                'amount' => number_format($totalAppCommission, 2),
            ],
        ];

        $cutHistory = DB::table('cut_history')
        ->select('cut_sale', 'cut_commision', 'cut_winners', 'cut_balance', 'add_datetime')
        ->where('user_id',  $user)
        ->orderByDesc('cut_id')
        ->limit(3)
        ->get();


        $jsonResponse = [
    'data' => $emparray ,
    'cutList' =>  $cutHistory,
    'success' => true,
    'msg'       => 'Get Successfully',

];



        return response()->json($jsonResponse);
    }

    return response()->json(['msg' => 'Invalid request'], 401);
}


// ...

public function SellerDashboard($user)
{
    if ($user->user_role == 'seller' ) {
        $date = now();
        $userId = $user->user_id;



        if ($user) {
            // Retrieve data using direct DB queries
          

// Fetch the last entry from cut_history for the user
$lastCutEntry = DB::table('cut_history')
    ->where('user_id', $userId)
    ->latest('add_datetime') // Get the latest entry based on add_datetime
    ->first();
   

    $finaltime = $lastCutEntry ? $lastCutEntry->add_datetime : now();
   

    // Use $finaltime in other queries
    $totalSold = DB::table('orders')
        ->where('user_id', $userId)
        ->where('lotterycollected', 0)
        ->where('adddatetime', '>', $finaltime) // Apply the condition on created_at or the appropriate datetime column
        ->count();
        
        //dd($totalSold);

    $totalCollected = DB::table('orders')
        ->where('user_id', $userId)
        ->where('lotterycollected', 0)
        ->where('adddatetime', '>', $finaltime)
        ->sum('grand_total');

    $totalCashInHand = DB::table('transactions')
        ->where('seller_id', $userId)
        ->where('balance', 1)
        ->where('transaction_add_date', '>', $finaltime)
        ->sum('credit') - DB::table('transactions')
        ->where('seller_id', $userId)
        ->where('balance', 1)
        ->where('transaction_add_date', '>', $finaltime)
        ->sum('debit');

    $totalWin = DB::table('order_item')
        ->whereIn('order_id', DB::table('orders')
            ->where('user_id', $userId)
            ->where('lotterycollected', 0)
            ->where('adddatetime', '>', $finaltime)
            ->pluck('order_id'))
        ->sum('winning_amount');


            $data = [
                [
                    'img' => asset('assets/images/1.png'),
                    'name' => 'Total Sold',
                    'spanishName' => 'Total vendido',
                    'amount' => number_format($totalCollected, 2)
                ],
                // ... other data items with date range filters ...
            ];

            if ($user->commission > 0) {
                $data[] = [
                        'img' => asset('assets/images/2.png'),
                        'name' => 'Seller Commision',
                        'spanishName' => 'Porcentaje De Comisión',
                        'amount' => $user->commission . '%'
                ];
                $data[] = [
                        'img' => asset('assets/images/3.png'),
                        'name' => 'Commision Amount',
                        'spanishName' => 'Comisión',
                        'amount' => number_format($totalCollected * $user->commission / 100, 2)
                ];
            }

            $data[] = [
                        'img' => asset('assets/images/4.png'),
                        'name' => 'Paid winning Number',
                        'spanishName' => 'Ganador Pagado', // total paid winniw numbers
                        'amount' => number_format($totalWin,2),
            ];

            $data[] = [
                        'img' => asset('assets/images/5.png'),
         	            'name' => 'Balance',
					 	'spanishName' => 'Efectivo', // Total amount(cash in hand )
					 	'amount' => number_format($totalCollected - $totalWin,2),
            ];

            if($totalCollected > 0 ){
                $saldo = number_format($totalCollected -$user->commission -$totalWin,2);
            }else{
                $saldo = "0";
            }

            $data[] = [
                        'img' => asset('assets/images/5.png'),
			            'name' => 'Cash',
					 	'spanishName' => 'Saldo',// seller commision in amount
					 	'amount' => $saldo,
            ];

            // ... (rest of the code remains the same)

            $cutHistory = DB::table('cut_history')
            ->select('cut_sale', 'cut_commision', 'cut_winners', 'cut_balance', 'add_datetime')
            ->where('user_id', $user->user_id)
            ->orderByDesc('cut_id')
            ->limit(3)
            ->get();


            $jsonResponse = [
        'data' => $data ,
        'cutList' =>  $cutHistory,
        'success' => true,
        'msg'       => 'Get Successfully',

    ];

            return response()->json($jsonResponse);


        }
    }
}



public function managerDashboard($user)
{
    // Ensure the user is a user
    if ($user->user_role !== 'manager') {
        abort(403, 'Unauthorized');
    }

    // Get the most recent cut from the management history
    $mostRecentCut = DB::table('cut_history')
        ->where('user_id', $user->user_id)
        ->orderBy('add_datetime', 'desc')
        ->first();
   //dd($mostRecentCut);
    // Set the date to show data from (using the most recent cut date or today's date)


    if ($mostRecentCut !== null) {
        $showFromDate = $mostRecentCut->add_datetime;
    } else {
        // Handle the case where no records were found
        // For example, you could set $showFromDate to today's date
        $showFromDate = date('Y-m-d'); // Assuming 'Y-m-d' format for date
    }


    //dd($showFromDate);
    // Retrieve seller IDs under the user
    $sellerIds = DB::table('users')
        ->where('added_user_id', $user->user_id)
        ->pluck('user_id')
        ->toArray();
   //dd($sellerIds);
    // ... other code

    // Build the data array with more meaningful column names
    $dashboardData = [
        'totalSold' => DB::table('orders')
        ->whereIn('user_id', $sellerIds)
        ->where('adddatetime', '>', $showFromDate)
        ->sum('grand_total'),

        'totalCollected' => DB::table('orders')
        ->whereIn('user_id', $sellerIds)
            ->where('adddatetime', '>', $showFromDate)
            ->sum('grand_total'),

        'totalSellers' => count($sellerIds),

        'totalCommission' => (DB::table('orders')
        ->whereIn('user_id', $sellerIds)
            ->where('adddatetime', '>', $showFromDate)
            ->sum('grand_total') / 100) * $user->commission,

        'totalPaid' => DB::table('transactions')
            ->whereIn('seller_id', $sellerIds)
            ->where('transaction_add_date', '>', $showFromDate)
            ->where('balance', 0)
            ->sum('debit'),

        'totalWin' => DB::table('order_item')
            ->join('orders', 'order_item.order_id', '=', 'orders.order_id')
            ->whereIn('orders.user_id', $sellerIds)
            ->where('orders.adddatetime', '>', $showFromDate)
            ->sum('order_item.winning_amount'),

        'cashInHand' => DB::table('transactions')
            ->whereIn('seller_id', $sellerIds)
            ->where('transaction_add_date', '>', $showFromDate)
            ->sum('credit') -
                DB::table('transactions')
                ->whereIn('seller_id', $sellerIds)
                ->where('transaction_add_date', '>', $showFromDate)
                ->sum('debit'),

        'appCommission' => DB::table('orders')
            ->whereIn('user_id', $sellerIds)
            ->where('adddatetime', '>', $showFromDate)
            ->sum('grand_total') * 0.005,
    ];

    $dashboardArray = [
        [
            'img' => asset('assets/images/1.png'),
            'name' => 'Total Sold',
            'spanishName' => 'Total vendido',
            'amount' => number_format($dashboardData['totalSold'], 2),
        ],
        [
            'img' => asset('assets/images/3.png'),
            'name' => ' Commision ',
            'spanishName' => 'Porcentaje De Comisión',// seller commision %
            'amount' =>  $user->commission."%",
        ],
        [
            'img' => asset('assets/images/2.png'),
            'name' => 'Total Sellers',
            'spanishName' => 'Total de vendedores',
            'amount' => number_format($dashboardData['totalSellers'], 2),
        ],
        [
            'img' => asset('assets/images/3.png'),
            'name' => 'Total Commission',
            'spanishName' => 'Total de comisiones',
            'amount' => number_format($dashboardData['totalCommission'], 2),
        ],
        [
            'img' => asset('assets/images/5.png'),
            'name' => 'Total Paid',
            'spanishName' => 'Total pagado',
            'amount' => number_format($dashboardData['totalPaid'], 2),
        ],
        [
            'img' => asset('assets/images/5.png'),
            'name' => 'Total Win',
            'spanishName' => 'Total ganado',
            'amount' => number_format($dashboardData['totalWin'], 2),
        ],
        [
            'img' => asset('assets/images/3.png'),
            'name' => 'Cash In Hand',
            'spanishName' => 'Dinero en mano',
            'amount' => number_format($dashboardData['cashInHand'], 2),
        ],
        [
            'img' => asset('assets/images/3.png'),
            'name' => 'App Commission',
            'spanishName' => 'Comisión de la aplicación',
            'amount' => number_format($dashboardData['appCommission'], 2),
        ],
    ];



    $cutHistory = DB::table('cut_history')
        ->select('cut_sale', 'cut_commision', 'cut_winners', 'cut_balance', 'add_datetime')
        ->where('user_id', $user->user_id)
        ->orderByDesc('cut_id')
        ->limit(3)
        ->get();



     $jsonResponse = [
        'data' => $dashboardArray ,
        'cutList' =>  $cutHistory,
        'success' => true,
        'msg'       => 'Get Successfully',

    ];

     return response()->json($jsonResponse);
}






public function collectBalance(Request $request)
{


        $user = $request->input('user_id');
        $addedUserId = auth()->user()->user_id;
        $balance = intval(str_replace(',', '', $request->input('balance')));
        $commission = $request->input('commission');
        $totalSale = $request->input('total_sale');
        $paidWinning = intval(str_replace(',', '', $request->input('paid_winning')));

        DB::beginTransaction();

        try {
            DB::table('orders')
                ->where('user_id', $user)
                ->where('lotterycollected', 0)
                ->update(['lotterycollected' => 1]);

            DB::table('transactions')->insert([
                'debit' => $commission ? $commission : 0,
                'credit' => 0,
                'balance' => 0,
                'seller_id' => $user,
                'transaction_remarks' => 'commission added'
            ]);

            if ($balance > 0) {
                DB::table('transactions')->insert([
                    'debit' => $balance ? $balance : 0,
                    'credit' => 0,
                    'balance' => 0,
                    'seller_id' => $user,
                    'transaction_remarks' => 'balance Collected'
                ]);
            } else {
                $balance2 = abs($balance);
                DB::table('transactions')->insert([
                    'credit' => $balance2,
                    'debit' => 0,
                    'balance' => 0,
                    'seller_id' => $user,
                    'transaction_remarks' => 'balance given'
                ]);
            }

            DB::table('cut_history')->insert([
                'user_id' => $user,
                'user_added_id' => $addedUserId,
                'cut_sale' => $totalSale,
                'cut_commision' => $commission,
                'cut_winners' => $paidWinning,
                'cut_balance' => $balance
            ]);

            DB::table('transactions')
                ->where('seller_id', $user)
                ->where('debit', '>', 0)
                ->update(['balance' => 1]);

            DB::commit();

            return response()->json([
                'success' => true,
                'msg' => 'balance Collected dashboard cleared',
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'msg' => $e->getMessage(),
            ]);
        }

}



    public function addWinningamountbySeller(Request $request){

        try {
            DB::beginTransaction();

            if(request()->filled(['order_item_id', 'winning_amount'])) {
                $userId = auth()->user()->user_id;
                $orderItemId = request('order_item_id');
                $winningAmount = request('winning_amount');
                $remark = "paid amount to ".$orderItemId;

                // Insert transaction record
                $inserted = DB::table('transactions')->insertGetId([
                    'debit' => $winningAmount,
                    'credit' => 0,
                    'balance' => 0,
                    'transaction_remarks' => $remark,
                    'seller_id' => $userId,
                    'order_item_id' => $orderItemId,
                ]);

                // Update order item
                if($inserted) {
                    DB::table('order_item')->where('order_item_id', $orderItemId)->update([
                        'transaction_paid_id' => $inserted
                    ]);

                    DB::commit();

                    $response = [
                        'success' => true,
                        'msg' => 'Paid this lottery'
                    ];
                } else {
                    $response = [
                        'success' => false,
                        'msg' => 'Failed to insert transaction'
                    ];
                }

                return response()->json($response);
            }
        } catch (QueryException $e) {
            DB::rollBack();
            $response = [
                'success' => false,
                'msg' => 'Database error: ' . $e->getMessage()
            ];
            return response()->json($response);
        } catch (\Exception $e) {
            DB::rollBack();
            $response = [
                'success' => false,
                'msg' => 'Error: ' . $e->getMessage()
            ];
            return response()->json($response);
        }

    }



}
