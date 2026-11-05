<?php

namespace Webkul\Admin\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use PDOException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Status codes the branded error page has copy of its own for.
     *
     * Any other code resolves to the generic 500 page, so an unexpected status never renders a
     * view whose `admin::app.errors.{code}` keys do not exist.
     *
     * @var array<int, int>
     */
    private const BRANDED_ERROR_CODES = [401, 403, 404, 419, 429, 500, 503];

    /**
     * Json error messages.
     *
     * @var array
     */
    protected $jsonErrorMessages = [];

    /**
     * Create handler instance.
     *
     * @return void
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->jsonErrorMessages = [
            '404' => trans('admin::app.errors.404.title'),
            '403' => trans('admin::app.errors.403.title'),
            '401' => trans('admin::app.errors.401.title'),
            '419' => trans('admin::app.errors.419.title'),
            '429' => trans('admin::app.errors.429.title'),
            '500' => trans('admin::app.errors.500.title'),
        ];
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  Request  $request
     * @return Response
     */
    public function render($request, Throwable $exception)
    {
        if (! config('app.debug')) {
            return $this->renderCustomResponse($exception);
        }

        return parent::render($request, $exception);
    }

    /**
     * Convert an authentication exception into a response.
     *
     * @param  Request  $request
     * @return Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->jsonErrorMessages[401]], 401);
        }

        return redirect()->guest(route('customer.session.index'));
    }

    /**
     * Render custom HTTP response.
     *
     * @return Response|null
     */
    private function renderCustomResponse(Throwable $exception)
    {
        /**
         * An expired CSRF token is a 419, not a server fault. `parent::render()` is what maps
         * `TokenMismatchException` to a 419 `HttpException`, and this override never reaches that
         * mapping, so the status has to be applied here — otherwise every expired form in the admin
         * reported itself as a 500.
         */
        if ($exception instanceof TokenMismatchException) {
            return $this->response(419);
        }

        if ($exception instanceof HttpException) {
            $statusCode = in_array($exception->getStatusCode(), self::BRANDED_ERROR_CODES)
                ? $exception->getStatusCode()
                : 500;

            return $this->response($statusCode);
        }

        if ($exception instanceof ValidationException) {
            return parent::render(request(), $exception);
        }

        if ($exception instanceof ModelNotFoundException) {
            return $this->response(404);
        } elseif ($exception instanceof PDOException || $exception instanceof \ParseError) {
            return $this->response(500);
        } else {
            return $this->response(500);
        }
    }

    /**
     * Return custom response.
     *
     * Only the status code reaches the view. The exception message, its trace and the file it was
     * raised in stay out of the rendered page and out of the JSON body, because an error page is
     * served to whoever can reach the URL.
     *
     * @param  int  $errorCode
     * @return mixed
     */
    private function response($errorCode)
    {
        if (request()->expectsJson()) {
            return response()->json([
                'message' => isset($this->jsonErrorMessages[$errorCode])
                    ? $this->jsonErrorMessages[$errorCode]
                    : trans('admin::app.common.something-went-wrong'),
            ], $errorCode);
        }

        /**
         * The view carries the status code too: answering 200 with an error page made a missing page
         * look like a working one to every cache, crawler and uptime check.
         */
        return response()->view('admin::errors.index', compact('errorCode'))->setStatusCode($errorCode);
    }
}
