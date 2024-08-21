<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\RequestUser;
use App\Mail\RequestConfirmation;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;


class UserController extends Controller
{
    //
    public function getNotifications(){
        try{
            
            $user = Auth()->user();
            
            $notifications = DB::table('notifications')->where('seller_id', $user->user_id)->orderBy('add_datetime', 'DESC')->get();
            
            if ($notifications->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No notifications found', 'data' => []], 404);
            }
            
            return response()->json(['success' => true, 'data' => $notifications], 200);
            
        }catch(\Exception $e){
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    public function readNotification(Request $request){
        try{
            
            $id = $request->input('notification_id');
            
        $notification = DB::table('notifications')->where('notification_id', $id)->first();
        
        // Check if the notification exists
        if (!$notification) {
            return response()->json(['success' => false, 'message' => 'Notification not found'], 404);
        }

        // Update the notification status to 'read'
        DB::table('notifications')->where('notification_id', $id)->update(['notification_status' => 'read']);
        
        return response()->json(['success' => true, 'message' => 'Notification marked as read'], 200);
        
        }catch(\Exception $e){
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function verifyUser(Request $request)
{
    try{
        // Get the authenticated user
    $user = Auth()->user();

    // Store the images in the storage folder and get their paths
    $cnicFrontPath = $request->file('cnic_front')->store('public/cnic_images');
    $cnicBackPath = $request->file('cnic_back')->store('public/cnic_images');
    $verifiedImagePath = $request->file('verified_image')->store('public/cnic_images');

   // Update the user record with the paths of the stored images
        DB::table('users')->where('user_id', $user->user_id)->update([
            'cnic_front' => Storage::url($cnicFrontPath),
            'cnic_back' => Storage::url($cnicBackPath),
            'verified_image' => Storage::url($verifiedImagePath),
        ]);

    return response()->json(['success' => true, 'message' => 'User verified successfully'], 200);
    }catch(\Exception $e){
        return response()->json(['success' => false, 'message' => $e->getMessage], 400);
    }
}

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
                    'msg'       => "User Already Exist",
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
                
            //     $cnicFrontPath = $request->file('cnic_front')->store('public/cnic_images');
            // $cnicBackPath = $request->file('cnic_back')->store('public/cnic_images');
            // $verifiedImagePath = $request->file('verified_image')->store('public/cnic_images');
                $requestData = [
                    'username'  => $request->input('username'),
                    'email'     => $request->input('useremail'),
                    'password'  => $request->input('password'),
                    'phone'     => $request->input('phone'),
                    'user_role' => strtolower($request->input('user_role')),
                    'address'   => $request->input('address'),
                ];

                
                if ($request->hasFile('cnic_front')) {
                $cnicFrontPath = $request->file('cnic_front')->store('public/cnic_images');
                $requestData['cnic_front'] = Storage::url($cnicFrontPath);
                }
    
                if ($request->hasFile('cnic_back')) {
                    $cnicBackPath = $request->file('cnic_back')->store('public/cnic_images');
                    $requestData['cnic_back'] = Storage::url($cnicBackPath);
                }
    
                if ($request->hasFile('verified_image')) {
                    $verifiedImagePath = $request->file('verified_image')->store('public/cnic_images');
                    $requestData['verified_image'] = Storage::url($verifiedImagePath);
                }
                
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

            public function approveUser(Request $request)
        {
            try {
                $user = Auth()->user();
                $requestedUserId = $request->input('req_user_id');
        
                // Fetch the user data from the request_user table
                $requestedUser = DB::table('request_user')->where('req_user_id', $requestedUserId)->first();
        
                if (!$requestedUser) {
                    return response()->json(['success' => false, 'message' => 'Requested user not found'], 404);
                }
        
                // Prepare data for insertion into the users table
                $userData = [
                    'username'          => $requestedUser->username,
                    'email'             => $requestedUser->email,
                    'password'          => md5($requestedUser->password),
                    'address'           => $requestedUser->address,
                    'phone'             => $requestedUser->phone,
                    'user_role'         => $requestedUser->user_role,
                    'commission'        => 0, // Assuming initial commission is 0
                    'added_user_id'     => $user->user_id, // Assuming the current authenticated user is adding the user
                    'status'            => $requestedUser->status, // Assuming the status is active on approval
                    'cnic_front'        => $requestedUser->cnic_front,
                    'cnic_back'         => $requestedUser->cnic_back,
                    'verified_image'    => $requestedUser->verified_image,
                ];
        
                // Insert the user data into the users table
                DB::table('users')->insert($userData);
        
                // Delete the user from the request_user table
                DB::table('request_user')->where('req_user_id', $requestedUserId)->delete();
        
                return response()->json(['success' => true, 'message' => 'User approved and added to users table successfully']);
            } catch (\Exception $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
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

public function userList(Request $request, $all = null)
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
        } elseif ($loggedInUser->user_role === 'manager') {
            $admins = User::select('user_id', 'username', 'email', 'phone', 'user_role', 'commission', 'status')
                ->where(function($query) use ($userId) {
                    $query->where('added_user_id', $userId)
                        //   ->orWhere('user_id', $userId)
                          ;
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
        } elseif ($loggedInUser->user_role === 'admin') {
            // Get admin details
    $adminDetails = User::select('user_id', 'username', 'email', 'phone', 'user_role', 'status')
        ->where('user_id', $userId)
        ->where('status', 1)
        ->first();

    // Get managers added by admin
    $managers = User::select('user_id', 'username', 'email', 'phone', 'user_role', 'status')
        ->where('added_user_id', $userId)
        ->where('user_role', 'manager')
        ->where('status', 1)
        ->get();

    // Get sellers directly added by admin
    $adminSellers = User::select('user_id', 'username', 'email', 'phone', 'user_role', 'status')
        ->where('added_user_id', $userId)
        ->where('user_role', 'seller')
        ->where('status', 1)
        ->get();

    // Get sellers added by managers
    $managersWithSellers = $managers->flatMap(function($manager) {
        return User::select('user_id', 'username', 'email', 'phone', 'user_role', 'status')
            ->where('added_user_id', $manager->user_id)
            ->where('user_role', 'seller')
            ->where('status', 1)
            ->get();
    });

    if ($all !== null) {
        // Create an array to hold all the data
        $responseData = [];
        
        // Add admin details first
        // $adminData = [
        //     'user_id' => $adminDetails->user_id,
        //     'username' => $adminDetails->username . ' (' . $adminDetails->user_role . ')',
        //     'email' => $adminDetails->email,
        //     'phone' => $adminDetails->phone,
        //     'user_role' => $adminDetails->user_role,
        //     'status' => $adminDetails->status,
        // ];
        
        // // Add admin details to the response data
        // $responseData[] = $adminData;
        
        // Merge sellers directly under the admin
        $adminSellers = collect($adminSellers)->map(function($seller) {
            $seller->username = $seller->username . ' (' . $seller->user_role . ')';
            return $seller;
        });
        $responseData = array_merge($responseData, $adminSellers->toArray());
        
        // Merge managers
        $managers = collect($managers)->map(function($manager) {
            $manager->username = $manager->username . ' (' . $manager->user_role . ')';
            return $manager;
        });
        $responseData = array_merge($responseData, $managers->toArray());
        
        // Merge sellers under the managers
        $managersWithSellers = collect($managersWithSellers)->map(function($seller) {
            $seller->username = $seller->username . ' (' . $seller->user_role . ')';
            return $seller;
        });
        $responseData = array_merge($responseData, $managersWithSellers->toArray());
    }else {
                $managersWithChildren = $managers->map(function($manager) {
        $sellers = User::select('user_id', 'username', 'email', 'phone', 'user_role', 'status')
            ->where('added_user_id', $manager->user_id)
            ->where('user_role', 'seller')
            ->where('status', 1)
            ->get()
            ->map(function($seller) {
                // Add user_role in parentheses to the seller's username
                $seller->username = $seller->username . ' (' . $seller->user_role . ')';
                $seller->children = []; // Add empty children array to each seller
                return $seller;
            });

        // Add user_role in parentheses to the manager's username
        $manager->username = $manager->username . ' (' . $manager->user_role . ')';
        
        // Attach the sellers as children to the manager
        $manager->children = $sellers->toArray();
        return $manager;
    });
    
    // Ensure admin sellers have empty children arrays and include user_role in username
    $adminSellersWithChildren = $adminSellers->map(function($seller) {
        // Add user_role in parentheses to the seller's username
        $seller->username = $seller->username . ' (' . $seller->user_role . ')';
        $seller->children = []; // Add empty children array to each seller
        return $seller;
    });

    // Prepare the response data including admin details
    $responseData = [[
        'user_id' => $adminDetails->user_id,
        'username' => $adminDetails->username . ' (' . $adminDetails->user_role . ')', // Add user_role in parentheses to the admin's username
        'email' => $adminDetails->email,
        'phone' => $adminDetails->phone,
        'user_role' => $adminDetails->user_role,
        'status' => $adminDetails->status,
        'children' => array_merge($adminSellersWithChildren->toArray(), $managersWithChildren->toArray())
    ]];
            }

            $jsonResponse = [
                'success' => true,
                'msg' => 'Get Successfully',
                'data' => $responseData
            ];
        } else {
            $admins = User::select('user_id', 'username', 'email', 'phone', 'user_role', 'commission', 'status')
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

    // Create a hash table using user_id as keys and initialize the children array
    foreach ($users as $user) {
        $user['children'] = [];
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
            $parent['children'][] = &$userHash[$user['user_id']];
        } elseif ($user['user_role'] === 'seller' && isset($userHash[$user['added_user_id']])) {
            // Seller is a child of manager
            $parent = &$userHash[$user['added_user_id']];
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
