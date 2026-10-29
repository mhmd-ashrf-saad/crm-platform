<?php

namespace Webkul\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Make a PDF download announce itself as a PDF.
 *
 * `DashboardController::exportPdf()` and every other caller of
 * `Webkul\Core\Traits\PDFHandler::downloadPDF()` build the file correctly, but the mPDF branch -- the
 * one taken for Arabic and Hebrew, where the directionality handling lives -- hands the bytes back
 * through `response()->streamDownload()`, which sets no MIME type of its own. The response therefore
 * keeps the framework default, `text/html; charset=utf-8`, and only the `Content-Disposition`
 * attachment header saves the download. API clients, PDF viewers that sniff `Content-Type`, and
 * corporate proxies all read the file as a web page.
 *
 * The two files that would be the natural place to fix this are upstream Krayin code, and this fork
 * must not edit upstream files: every `2.2` merge would collide on them. So the correction is made
 * once here, for every route, from a package-level middleware.
 *
 * It only ever touches a response that is already a PDF attachment -- the filename in
 * `Content-Disposition` ends in `.pdf`. An HTML page, a JSON response or a CSV export never matches,
 * so a wrong route match cannot damage a normal page.
 */
class ForcePdfContentType
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        if (! $response instanceof Response) {
            return $response;
        }

        $disposition = strtolower((string) $response->headers->get('content-disposition'));

        if ($disposition === '' || ! str_contains($disposition, '.pdf')) {
            return $response;
        }

        if (str_starts_with(strtolower((string) $response->headers->get('content-type')), 'application/pdf')) {
            return $response;
        }

        $response->headers->set('Content-Type', 'application/pdf');

        return $response;
    }
}
