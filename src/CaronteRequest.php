<?php

/**
 * @author Gabriel Ruelas
 * @license MIT
 * @version 1.0.0
 *
 */

namespace Equidna\Caronte;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Equidna\Toolkit\Helpers\RouteHelper;
use Equidna\Toolkit\Helpers\ResponseHelper;
use Equidna\Caronte\Facades\Caronte;
use Exception;
use Illuminate\Support\Facades\View;

/**
 * This class is responsible for making basic requests to the Caronte server.
 */

class CaronteRequest
{
    private function __construct()
    {
        //ONLY STATIC METHODS ALLOWED
    }

    /**
     * Logs in a user with their password.
     *
     * @param Request $request The request object containing the user's email, password, and callback URL.
     * @return Response|RedirectResponse The response object or a redirect response.
     */
    public static function userPasswordLogin(Request $request): Response|RedirectResponse
    {
        $decoded_url  = base64_decode($request->callback_url);

        if (!empty($decoded_url) && $decoded_url !== '\\') {
            $callback_url = $decoded_url;
        } else {
            $callback_url = config('caronte.SUCCESS_URL');
        }

        try {
            $caronte_response = HTTP::withOptions(
                [
                    'verify' => !config('caronte.ALLOW_HTTP_REQUESTS')
                ]
            )->post(
                config('caronte.URL') . 'api/' . config('caronte.VERSION') . '/login',
                [
                    'email'    => $request->email,
                    'password' => $request->password,
                    'app_id'   => config('caronte.APP_ID')
                ]
            );

            if ($caronte_response->failed()) {
                throw new RequestException(response: $caronte_response);
            }

            $token  = CaronteToken::validateToken(raw_token: $caronte_response->body());
        } catch (RequestException $e) {
            return ResponseHelper::badRequest(message: $e->response->body());
        } catch (Exception $e) {
            return ResponseHelper::unautorized($e->getMessage());
        }

        if (RouteHelper::isAPI()) {
            return response($token->toString(), 200);
        }

        Caronte::saveToken($token->toString());

        return redirect($callback_url)->with(['success' => 'Sesión iniciada con éxito']);
    }

    /**
     * Handles the password recovery request.
     *
     * This method sends a POST request to the Caronte API to initiate the password recovery process.
     * It handles both API and web responses based on the request type.
     *
     * @param Request $request The incoming request containing the user's email.
     * @return Response|RedirectResponse Returns a response for API requests or a redirect response for web requests.
     * @throws RequestException If the HTTP request to the Caronte API fails.
     * @throws Exception If any other exception occurs during the process.
     */
    public static function passwordRecoverRequest(Request $request): Response|RedirectResponse
    {
        try {
            $caronte_response = HTTP::withOptions(
                [
                    'verify' => !config('caronte.ALLOW_HTTP_REQUESTS')
                ]
            )->post(
                config('caronte.URL') . 'api/' . config('caronte.VERSION') . '/password/recover',
                [
                    'email'   => $request->email,
                    'app_id'  => config('caronte.APP_ID'),
                    'app_url' => base64_encode(config('app.url'))
                ]
            );

            if ($caronte_response->failed()) {
                throw new RequestException(response: $caronte_response);
            }

            $response = $caronte_response->body();
        } catch (RequestException $e) {
            return ResponseHelper::badRequest($e->response->body());
        } catch (Exception $e) {
            return ResponseHelper::badRequest($e->getMessage());
        }

        if (RouteHelper::isAPI()) {
            return response($response, 200);
        }

        return redirect(config('caronte.LOGIN_URL'))->with(['success' => $response]);
    }

    /**
     * Validates a password recovery token by making an HTTP request to the Caronte API.
     *
     * @param Request $request The incoming HTTP request.
     * @param string $token The password recovery token to validate.
     * @return Response|View Returns a Response object if the request is an API call,
     *                       or a View object if the request is a web call.
     *
     * @throws RequestException If the HTTP request to the Caronte API fails.
     * @throws Exception If any other exception occurs during the process.
     */
    public static function passwordRecoverTokenValidation(Request $request, $token): Response|View
    {
        try {
            $caronte_response = HTTP::withOptions(
                [
                    'verify' => !config('caronte.ALLOW_HTTP_REQUESTS')
                ]
            )->get(
                config('caronte.URL') . 'api/' . config('caronte.VERSION') . '/password/recover/' . $token
            );

            if ($caronte_response->failed()) {
                throw new RequestException($caronte_response);
            }

            $response = $caronte_response->body();
        } catch (RequestException $e) {
            return ResponseHelper::badRequest($e->response->body());
        } catch (Exception $e) {
            return ResponseHelper::badRequest($e->getMessage());
        }

        if (RouteHelper::isAPI()) {
            return response($response, 200);
        }

        return View('caronte::password-recover');
    }

    /**
     * Handles the password recovery process by sending a request to the Caronte API.
     *
     * @param \Illuminate\Http\Request $request The incoming request containing the new password.
     * @param string $token The token used for password recovery.
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Http\Client\RequestException If the HTTP request fails.
     * @throws \Exception If any other exception occurs.
     */
    public static function passwordRecover(Request $request, $token): Response|RedirectResponse
    {
        try {
            $caronte_response = HTTP::withOptions(
                [
                    'verify' => !config('caronte.ALLOW_HTTP_REQUESTS')
                ]
            )->post(
                config('caronte.URL') . 'api/' . config('caronte.VERSION') . '/password/recover/' . $token,
                [
                    'password'              => $request->password,
                    'password_confirmation' => $request->password
                ]
            );

            if ($caronte_response->failed()) {
                throw new RequestException($caronte_response);
            }

            $response = $caronte_response->body();
        } catch (RequestException $e) {
            return ResponseHelper::badRequest($e->response->body());
        } catch (Exception $e) {
            return ResponseHelper::badRequest($e->getMessage());
        }

        if (RouteHelper::isAPI()) {
            return response($response, 200);
        }

        return redirect(config('caronte.LOGIN_URL'))->with(['success' => $response]);
    }

    /**
     * Sends a two-factor token request.
     *
     * @param Request $request The request object containing the callback URL and email.
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse The response from the server or a redirect response.
     *
     * @throws RequestException If the request to the server fails.
     */
    public static function twoFactorTokenRequest(Request $request): Response|RedirectResponse
    {
        try {
            $caronte_response = HTTP::withOptions(
                [
                    'verify' => !config('caronte.ALLOW_HTTP_REQUESTS')
                ]
            )->post(
                config('caronte.URL') . 'api/' . config('caronte.VERSION') . '/2fa',
                [
                    'email'           => $request->email,
                    'app_id'          => config('caronte.APP_ID'),
                    'application_url' => config('app.url'),
                ]
            );

            if ($caronte_response->failed()) {
                throw new RequestException($caronte_response);
            }

            if (RouteHelper::isAPI()) {
                return response("Authentication email sent to " . $request->email, 200);
            }

            return back()->with(['success' => $caronte_response->body()]);
        } catch (Exception $e) {
            return ResponseHelper::badRequest($e->getMessage());
        }
    }

    /**
     * Logs in the user using a two-factor authentication token.
     *
     * @param Request $request The HTTP request object.
     * @param string $token The two-factor authentication token.
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse The response from the server or a redirect response.
     */
    public static function twoFactorTokenLogin(Request $request, $token): Response|RedirectResponse
    {
        $decoded_url  = base64_decode($request->callback_url);

        if (!empty($decoded_url) && $decoded_url !== '\\') {
            $callback_url = $decoded_url;
        } else {
            $callback_url = config('caronte.SUCCESS_URL');
        }

        try {
            $caronte_response = HTTP::withOptions(
                [
                    'verify' => !config('caronte.ALLOW_HTTP_REQUESTS')
                ]
            )->get(
                config('caronte.URL') . 'api/' . config('caronte.VERSION') . '/2fa/' . $token
            );

            if ($caronte_response->failed()) {
                throw new RequestException($caronte_response);
            }

            $token = CaronteToken::validateToken(raw_token: $caronte_response->body());
        } catch (RequestException $e) {
            return ResponseHelper::badRequest($e->response->body());
        } catch (Exception $e) {
            return ResponseHelper::unautorized($e->getMessage());
        }

        if (RouteHelper::isAPI()) {
            return response($token->toString(), 200);
        }

        Caronte::saveToken($token->toString());

        return redirect($callback_url)->with('success', 'Sesión iniciada con éxito');
    }

    /**
     * Logs out the user.
     *
     * @param Request $request The request object.
     * @param bool $logout_all_sessions (optional) Whether to logout from all sessions. Default is false.
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse The response object or redirect response.
     */
    public static function logout(Request $request, $logout_all_sessions = false): Response|RedirectResponse
    {
        try {
            $caronte_response = HTTP::withOptions(
                [
                    'verify' => !config('caronte.ALLOW_HTTP_REQUESTS')
                ]
            )->withHeaders(
                [
                    'Authorization' => "Bearer " . Caronte::getToken()->toString()
                ]
            )->get(
                config('caronte.URL') . 'api/' . config('caronte.VERSION') . '/logout' . ($logout_all_sessions ? 'All' : '')
            );

            if ($caronte_response->failed()) {
                throw new RequestException($caronte_response);
            }

            $response_array = ['success' => 'Sesión cerrada con éxito'];
        } catch (RequestException $e) {
            $response_array = ['error' => $e->response->body()];
        }

        Caronte::clearToken();

        if (RouteHelper::isAPI()) {
            return response('Logout complete', 200);
        }

        return redirect(config('caronte.LOGIN_URL'))->with($response_array);
    }
}
