<?php

namespace App\Http\Controllers\Api;

use App\Contracts\BriInboundAuthenticatorInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BriVaInboundController extends Controller
{
    public function __construct(private readonly BriInboundAuthenticatorInterface $authenticator)
    {
    }

    public function token(Request $request)
    {
        $clientId = (string) $request->input('client_id');
        $clientSecret = (string) $request->input('client_secret');

        $token = $this->authenticator->issueToken($clientId, $clientSecret);

        if ($token === null) {
            return response()->json([
                'responseCode' => '4017300',
                'responseMessage' => 'Unauthorized Client',
            ], 401);
        }

        return response()->json([
            'accessToken' => $token,
            'tokenType' => 'BearerToken',
            'expiresIn' => '899',
        ]);
    }

    protected function bearerToken(Request $request): string
    {
        return (string) str($request->header('Authorization', ''))->after('Bearer ');
    }
}
