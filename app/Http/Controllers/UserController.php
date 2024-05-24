<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\RequestUser;
use App\Mail\RequestConfirmation;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    //


    public function addusers(Request $request ,  $user_id = null)
    {
        //dd($request);
        if ($request->filled(['email', 'password','user_role'])) {

            $user = auth()->user();

            try {
                $userData = [
                    'username'      => $request->input('username'),
                    'email'         => $request->input('email'),
                    'password'      => md5($request->input('password')),
                    'phone'         => $request->input('phone'),
                    'user_role'     => strtolower($request->input('user_role')),
                    'commission'    => $request->input('commission'),
                    'added_user_id' => $user->user_id,
                    'address'       => $request->input('address'),
                ];

                if ($request->filled('req_user_id')) {
                    // Assuming 'request_user' is your table for storing requests
                    // Remove the request entry if req_user_id is provided
                    // Replace 'request_user' with your actual table name
                    DB::table('request_user')->where('req_user_id', $request->input('req_user_id'))->delete();
                }
                if(!empty($user_id)){
                    DB::table('users')->where('user_id', $user_id)->update($userData);
                }else{
                // Insert user data into the 'users' table
                DB::table('users')->insert($userData);
                }
                $response = [
                    'success' => true,
                    'msg'       => ($user_id !== null) ? 'User Updated Successfully' : 'User Added Successfully',

                ];
            } catch (\Exception $e) {
                $response = [
                    'success' => false,
                    'msg'       => $e->getMessage(),
                ];
            }

            return response()->json($response);
        } else {
            return response()->json([
                'success' => false,
                'msg'       => 'Invalid request parameters',
            ]);
        }
    }




    public function requestUser(Request $request ){
        if ($request->filled(['username','useremail', 'password'])) {
            try {
                $requestData = [
                    'username'  => $request->input('username'),
                    'email'     => $request->input('useremail'),
                    'password'  => $request->input('password'),
                    'phone'     => $request->input('phone'),
                    'user_role' => strtolower($request->input('user_role')),
                    'address'   => $request->input('address'),
                ];

                // Insert request user data into the 'request_user' table
                RequestUser::create($requestData);

                // Call the sendConfirmationEmail function
        //$this->sendConfirmationEmail($request);
                $response = [
                    'success' => true,
                    'msg'       => 'Information added, we will update you soon',
                ];
            } catch (\Exception $e) {
                $response = [
                    'success' => false,
                    'msg'       => $e->getMessage(),
                ];
            }

            return response()->json($response);
        } else {
            return response()->json([
                'success' => false,
                'msg'       => 'Invalid request parameters',
            ]);
        }
    }


    public function requestUserList(Request $request  ){


        $requestedUsers = RequestUser::orderBy('created_at', 'desc')->get();

    return response()->json([
        'success' => true,
        'msg' => 'All requested List',
        'data' => $requestedUsers]);


    }



     function sendConfirmationEmail(Request $request)
{
    // Assume $userData contains the necessary user information


    $userData = [
        'username' => $request->input('username'),
        'email' => $request->input('useremail'),
        'phone' =>  $request->input('phone'),
        'user_role' =>  $request->input('userrole'),
        'address' => $request->input('address'),
    ];

    // Send the email
    Mail::to($userData['email'])->send(new RequestConfirmation($userData));

    // Optionally, you can check if the email was sent successfully
    if (count(Mail::failures()) > 0) {
        return response()->json([
            'is_status' => 0,
            'msg' => 'Failed to send confirmation email',
        ]);
    }

    return response()->json([
        'is_status' => 1,
        'msg' => 'Confirmation email sent successfully',
    ]);
}

// user list based on user role

public function userList(Request $request)
{
    $userId = auth()->user()->user_id;
    $loggedInUser = User::find($userId);

    if ($loggedInUser) {
        if ($loggedInUser->user_role === 'superadmin') {
            $users = User::select('user_id', 'username', 'email', 'phone', 'user_role', 'added_user_id','status')
                ->where('status', 1)
                ->where('user_id', '!=', $userId)
                ->orderBy('user_role', 'ASC')
                ->get();
            //dd($users);
            $userTree = $this->buildUserTree($users->toArray(), null);

            $jsonResponse = [
                'success' => true,
                'msg' => 'Get Successfully',
                'data' => $userTree
            ];
        } else {
            $admins = User::select('user_id', 'username', 'email', 'phone', 'user_role', 'commission','status')
            ->where(function($query) use ($userId) {
                $query->where('added_user_id', $userId)
                      ->orWhere('user_id', $userId);
            })
            ->where('status', 1)
            ->orderBy('user_role', 'ASC')
            ->get();


            $adminsArray = $admins->toArray();

            $jsonResponse = [
                'success' => true,
                'msg' => 'Get Successfully',
                'data' => $adminsArray
            ];
        }

        return response()->json($jsonResponse);
    }
}




public function changePassword(Request $request)
{
    $validator = validator($request->all(), [
        'current_password' => 'required',
        'new_password' => 'required|min:8',
        'confirm_password' => 'required|same:new_password',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => true,
            'msg' => $validator->errors()->first(),
            'error' => 'error',

        ], 422);
    }

    if(!empty($request->user_id)){
        $userId = $request->user_id;
        $user = User::find($userId);
    }else{
    $user = auth()->user();
    }
    // Check if the current password matches the one in the database (for MD5 hashed passwords)
    if (md5($request->current_password) !== $user->password) {
        return response()->json([
            'success' => true,
            'msg' => 'Current password is incorrect.',
            'error' => 'error',

        ], 401);
    }



    // Update the user's password
    $user->update([
        'password' => md5($request->new_password),
    ]);

    return response()->json([
        'success' => true,
        'msg' => 'Password changed successfully.',
        'user_id' => $user->user_id,
    ]);


}







// private funcations

private function buildUserTree($users)
{
    $userHash = [];

    // Create a hash table using user_id as keys
    foreach ($users as $user) {
        $userHash[$user['user_id']] = $user;
    }

    $tree = [];

    foreach ($users as $user) {
        if ($user['user_role'] === 'admin') {
            // Admin is a root element
            $tree[] = &$userHash[$user['user_id']];
        } elseif ($user['user_role'] === 'manager' && isset($userHash[$user['added_user_id']])) {
            // Manager is a child of admin
            $parent = &$userHash[$user['added_user_id']];
            if (!isset($parent['children'])) {
                $parent['children'] = [];
            }
            $parent['children'][] = &$userHash[$user['user_id']];
        } elseif ($user['user_role'] === 'seller' && isset($userHash[$user['added_user_id']])) {
            // Seller is a child of manager
            $parent = &$userHash[$user['added_user_id']];
            if (!isset($parent['children'])) {
                $parent['children'] = [];
            }
            $parent['children'][] = &$userHash[$user['user_id']];
        }
    }

    return $tree;
}

//edit user only commsion and status


public function editUser(Request $request, $userId)
{
    try {
        // Validate the incoming request data
        $validatedData = $request->validate([
            'commission' => 'required',
            'status' => 'required',
        ]);

        // Prepare the user data for update
        $userData = [
            'commission' => $validatedData['commission'],
            'status' => $validatedData['status'],
        ];

        // Update the user attributes using the DB facade
        DB::table('users')
            ->where('user_id', $userId)
            ->update($userData);



        // Return a response indicating success
        return response()->json([
            'success' => true,
            'msg' => 'User updated successfully',

        ], 200);
    } catch (\Exception $e) {
        // Log the error
        \Log::error('Error updating user: ' . $e->getMessage());

        // Return an error response
        return response()->json([
            'success' => false,
            'msg' => 'Failed to update user.'], 500);
    }
}



}
