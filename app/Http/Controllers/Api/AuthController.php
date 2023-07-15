<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\VerificationEmail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;


class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->messages()], 400);
        }

        $user = User::where('email', $request->email)->exists();

        if ($user) {
            return response()->json(['message' => 'Email already taken'], 409);
        }

        DB::beginTransaction();
        try {
            //send code
            $verificationCode = random_int(1000, 9999);
            // $expiration = Carbon::now()->addMinutes(5)->translatedFormat('d F Y H:i:s');
            $expiration = Carbon::now()->addMinutes(5);

            $user = User::create([
                'email' => $request->email,
                'verify_code' => $verificationCode,
                'verify_exprired_at' => $expiration,
            ]);
            DB::commit();
            Mail::to($request->email)->send(new VerificationEmail($data, $verificationCode, $expiration));
            return response()->json([
                'message' => 'Succes',
                "code_verification" => $verificationCode,
                "expired_code_at" => $expiration
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function verifyCode(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'email' => 'required|email',
            'verify_code' => 'required|digits:4'
        ]);
        //cek validasi
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->messages()], 400);
        }
        //get user
        $user = User::where('email', $request->email)->first();

        $currentDateTime = Carbon::now();
        $expirationDateTime = Carbon::parse($user->verify_exprired_at);

        if ($currentDateTime->lte($expirationDateTime)) {
            if ($request->verify_code == $user->verify_code) {
                DB::beginTransaction();
                try {
                    $user->email_verified_at = $currentDateTime;
                    $user->save();

                    DB::commit();
                    return response()->json([
                        'message' => 'Succes, Silahkan Melanjutkan Registrasi',
                    ], 200);
                } catch (\Throwable $th) {
                    DB::rollback();
                    return response()->json(['message' => $th->getMessage()], 500);
                }
            }
            return response()->json(['errors' => 'Maaf Code Anda Tidak cocok'], 400);
        } else {
            return response()->json(['errors' => 'Code Anda Telah Expired'], 400);
        }
    }
}