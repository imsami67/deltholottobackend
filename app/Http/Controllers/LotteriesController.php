<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\User;

use App\Models\Lottery; // Adjust the namespace and path based on your actual model location

class LotteriesController extends Controller
{
    public function addLottery(Request $request, $lotteryId = null)
    {

        $uniVeriable = $lotteryId;

        //dd($request);
        if ($request->filled(['lot_name', 'mul_num' , 'is_open'])) {
            $user = auth()->user();


            // $weekdays = $request->input('weekdays');
            // //dd($request->input('weekdays'));
            // $decodedData = $weekdays;
             $weekdays = $request->input('weekdays'); // ["Cada dia", "Lunes"]

    // Check if $weekdays is an array
    if (is_array($weekdays)) {
    
        // Convert the array to a comma-separated string
        $weekdaysString = implode(', ', $weekdays); // "Cada dia, Lunes"
    }else{
        $weekdaysString = str_replace(["[", "]", "'"], "", $weekdays); 
// Result: "Cada dia, Lunes, Martes"

        //$weekdaysString = json_decode(json_encode($request->input('weekdays'))); // ["Cada dia", "Lunes"]
        
    }
            





            $lotData = [
                'lot_name'      => $request->input('lot_name'),
                'multiply_number' => $request->input('mul_num'),
                'winning_type'  => $request->input('winning_type'),
                'user_added_id' =>  $user->user_id,
                'lot_opentime'  => $request->input('fromtime'),
                'lot_closetime' => $request->input('totime'),
                'lot_colorcode' => $request->input('colorcode'),
                'lot_weekday'   => $weekdaysString,
                'is_open'   => $request->input('is_open'),
            ];

            // Handle image upload
            if ($request->hasFile('image')) {


                $imgName = uniqid() . '.' . $request->file('image')->getClientOriginalExtension();
                $request->file('image')->storeAs('public/images', $imgName);
                $imgUrlForApi = Storage::url('images/' . $imgName);

                $lotData['img_url'] = $imgUrlForApi;
            }

            // Check if editing an existing lottery or adding a new one
            if ($lotteryId !== null) {
                // Editing an existing lottery

                $lotData['user_edited_id'] = $user->user_id;
                //dd($lotData);
                DB::table('lotteries')->where('lot_id', $lotteryId)->update($lotData);

                 $lotteryDetails = DB::table('lotteries')->where('lot_id', $lotteryId)->first();
            } else {
                // Adding a new lottery
                DB::table('lotteries')->insert($lotData);
                $lotteryId = DB::getPdo()->lastInsertId();
                $lotteryDetails = DB::table('lotteries')->where('lot_id', $lotteryId)->first();
            }

            $response = [
                'data' => [
                    'lottery_details' => $lotteryDetails,
                ],
                'success' => true,
                'msg'       => (empty($uniVeriable)) ? 'Lottery Added Successfully' : 'Lottery Updated Successfully',
            ];
        } else {

            $response = [
                'success' => false,
                'msg'       => 'Invalid request parameters',
            ];
        }

        return response()->json($response);
    }


    public function deleteLottery(Lottery $lotteryId)
{

   // dd($lottery);

    try {
        DB::table('lotteries')->where('lot_id', $lotteryId)->delete();

        $response = [
            'success' => true,
            'msg'       => 'Lottery deleted successfully',
        ];
    } catch (\Exception $e) {
        $response = [
            'success' => false,
            'msg'       => 'Error deleting lottery: ' . $e->getMessage(),
        ];
    }

    return response()->json($response);
}



//lotteries list all
public function getLotteriesListAll($lotteryId = null)
{

    $baseUrl = url('/'); // Assuming you want to use the base URL of your Laravel application
    $userRole = auth()->user()->user_role;
    $userId = auth()->user()->user_id;
    //dd($userId);

    $adminIdThis = $this->getAdminId($userId);

    //dd($adminIdThis);
    $adminIdThis = $this->getAdminId($userId);

    $query = DB::table('lotteries')
        ->select('lot_id', 'lot_name AS name', 'is_open', 'multiply_number', 'img_url', 'winning_type', 'lot_opentime', 'lot_closetime','lot_colorcode')
        ->when($userRole != 'superadmin', function ($query) use ($adminIdThis) {
            return $query->where('user_added_id', $adminIdThis);
        })
        ->get();




        $lotteries = $query->map(function ($lottery) use ($baseUrl) {
            $days = DB::table('lotteries')->where('lot_id', $lottery->lot_id)->value('lot_weekday');
            //dd($days);
            
            $lottery->lot_weekday = $days ? explode(',', $days) : ['Cada dia'];
            //  $days = str_replace("'", "", $days);
            //   $lottery->lot_weekday = json_decode($days); 
              
              
            // Concatenate base URL with img_url
            $lottery->img_url = $baseUrl . $lottery->img_url;

            return $lottery;
        });


    if ($lotteryId) {
        $lotId = request('lot_id');
        $lot = DB::table('lotteries')->where('lot_id', $lotId)->first();
        return response()->json($lot);
    }

    return response()->json([

    'success' => true,
    'msg' => 'Lottery List',
    'data' => $lotteries,
]);
}



public function getLotteriesListAllWithTime()
{

    $user = auth()->user();

    $userRole = auth()->user()->user_role;
    $userId = auth()->user()->user_id;

    if (!$user) {
        return response()->json([
            'success' => false,
            'msg' => 'Invalid user_id',
            'timenow' => now()->format('H:i:s')
        ]);
    }

    // if ($user->user_role === 'admin') {
    //     $thisAdminId = $userId;
    // } else {
    //     $manager = User::find($user->added_user_id);
    //     if ($manager && $manager->user_role === 'manager') {
    //         $admin = User::find($manager->added_user_id);
    //         $thisAdminId = $admin ? $admin->user_id : null;
    //     } else {
    //         $thisAdminId = null;
    //     }
    // }

    if ($user->user_role === 'admin') {
        $thisAdminId = $userId;
    } elseif ($user->user_role === 'manager') {
        $manager = User::find($user->added_user_id);

        if ($manager && $manager->user_role === 'manager') {
            // If the user is a manager, find the admin of the manager
            $admin = User::find($manager->added_user_id);
            $thisAdminId = $admin ? $admin->user_id : null;
        } else {
            // If the user is a manager but their superior is not a manager, set to null
            $thisAdminId = null;
        }
    } elseif ($user->user_role === 'seller') {
        // If the user is a seller, check if added by admin or manager

        $addedByUser = User::find($user->added_user_id);
        //dd($addedByUser);
        if ($addedByUser && $addedByUser->user_role === 'admin') {
            // If added by admin, set the admin ID
            //dd($addedByUser);
            $thisAdminId = $addedByUser->user_id;

        } elseif ($addedByUser && $addedByUser->user_role === 'manager') {
            // If added by manager, find the admin of the manager
            $admin = User::find($addedByUser->added_user_id);
            $thisAdminId = $admin ? $admin->user_id : null;
        } else {
            // If the added user is not found or is not admin/manager, set to null
            $thisAdminId = null;
        }
    } else {
        // For any other role, set to null
        $thisAdminId = null;
    }




   // dd($thisAdminId);
    if (!$thisAdminId) {
        return response()->json([
            'success' => false,
            'msg' => 'Invalid user role or hierarchy',
            'timenow' => now()->format('H:i:s')
        ]);
    }

    date_default_timezone_set("America/Guatemala");
    $serverTimeWithGuatemala = now()->format('H:i:s');

    $daysArr = [
        'everyday' => 'Cada dia',
        'Monday' => 'Lunes',
        'Tuesday' => 'Martes',
        'Wednesday' => 'Miercoles',
        'Thursday' => 'Juevez',
        'Friday' => 'Viernes',
        'Saturday' => 'Sabado',
        'Sunday' => 'Domingo',
    ];

    // Before the query execution

    $query = DB::table('lotteries')
    ->select(
        'lot_id',
        'lot_name AS name',
        'is_open',
        'multiply_number',
        'img_url',
        'winning_type',
        'lot_opentime',
        'lot_closetime',
        'user_added_id',
        'lot_colorcode',
        DB::raw("
            CASE
                WHEN lot_colorcode = '' THEN 'Color(0xff1cff19)'
                WHEN lot_colorcode IS NULL THEN 'Color(0xffEAF8A3)'
                ELSE lot_colorcode
            END AS colorcode
        ")
    )
    ->orWhere(function ($query) use ($daysArr, $serverTimeWithGuatemala) {
        $query->where(function ($query) use ($daysArr, $serverTimeWithGuatemala) {
            $query->where('lot_weekday', $daysArr[now()->format('l')])
                ->where(function ($query) use ($daysArr, $serverTimeWithGuatemala) {
                    $query->where('lot_closetime', '>', $serverTimeWithGuatemala)
                        ->orWhere(function ($query) use ($daysArr, $serverTimeWithGuatemala) {
                            $query->where('lot_closetime', '<', $serverTimeWithGuatemala)
                                ->where('lot_closetime', '>', now()->subHours(3)->format('H:i:s'));
                        });
                })
                ->orWhere('lot_weekday', '!=', $daysArr[now()->format('l')]);
        })
        ->orWhere(function ($query) use ($daysArr, $serverTimeWithGuatemala) {
            $query->where('winning_type', 1)
                ->where('lot_opentime', '<', $serverTimeWithGuatemala)
                ->where('lot_closetime', '>', $serverTimeWithGuatemala);
        })
        // ->orWhere(function ($query) use ($daysArr, $serverTimeWithGuatemala) {
        //     $query->where('winning_type', 7)
        //         ->where(function ($query) use ($daysArr, $serverTimeWithGuatemala) {
        //             $query->where('lot_weekday', $daysArr[now()->format('l')])
        //                 ->where(function ($query) use ($daysArr, $serverTimeWithGuatemala) {
        //                     $query->where('lot_closetime', '>', $serverTimeWithGuatemala)
        //                         ->orWhere(function ($query) use ($daysArr, $serverTimeWithGuatemala) {
        //                             $query->where('lot_closetime', '<', $serverTimeWithGuatemala)
        //                                 ->where('lot_closetime', '>', now()->subHours(3)->format('H:i:s'));
        //                         });
        //                 })
        //                 ->orWhere('lot_weekday', '!=', $daysArr[now()->format('l')]);
        //         });
        // })
        //demo start
        ->orWhere(function ($query) use ($daysArr, $serverTimeWithGuatemala) {
    $query->where('winning_type', 7)
        ->orWhere('lot_weekday', '!=', now()->format('l'));
})
     //demo end
        ->orWhere(function ($query) use ($serverTimeWithGuatemala) {
            $query->where('winning_type', 1)
                ->where('lot_opentime', '<', $serverTimeWithGuatemala)
                ->where('lot_closetime', '>', $serverTimeWithGuatemala);
        });
    })
    ->where('user_added_id', $thisAdminId)
    ->get();









    if ($query->isNotEmpty()) {

        $lotteries = $query->map(function ($lottery) {
            $days = DB::table('lotteries')->where('lot_id', $lottery->lot_id)->value('lot_weekday');
            $lottery->lot_weekday = $days ? explode(',', $days) : ['Cada dia'];

             // Concatenate base URL with img_url
             $baseUrl = url('/');
    $lottery->img_url = $baseUrl . $lottery->img_url;
            return $lottery;
        });
        //dd($lotteries);
        return response()->json([

            'timenow' => now()->format('H:i:s'),
            'success' => true,
            'msg' => 'Lotteries get',
            'data' => $lotteries,

        ]);
    } else {
        return response()->json([
            'timenow' => now()->format('H:i:s'),
            'success' => true,
            'msg' => 'Lotteries not opened yet',

        ]);
    }
}


public function getAdminId($userId)
{
    $user = User::find($userId);

    if (!$user) {
        return null; // User not found
    }

    if ($user->user_role === 'admin') {
        return $user->user_id; // Return the user ID if the user is an admin
    } elseif ($user->user_role === 'manager' || $user->user_role === 'seller') {
        $addedByUser = User::find($user->added_user_id);

        if ($addedByUser) {
            if ($addedByUser->user_role === 'admin') {
                return $addedByUser->user_id; // Return the admin ID if the added user is an admin
            } elseif ($addedByUser->user_role === 'manager') {
                $admin = User::find($addedByUser->added_user_id);
                if ($admin && $admin->user_role === 'admin') {
                    return $admin->user_id; // Return the admin ID if the added user is a manager and their superior is an admin
                }
            }
        }
    }

    return null; // Return null if the admin ID is not found
}


}
