<?php

/*
 * Sijilna CRM -- one place for the application-level upload ceilings.
 *
 * PHP itself refuses a file larger than `upload_max_filesize` before the framework ever sees
 * it, and rejects a whole request larger than `post_max_size` before validation runs. Those are
 * per-directory INI settings and cannot be changed from application code, so the numbers here
 * only mean something when they are at least as large as PHP's own limits:
 *
 *   host PHP (dev):        deploy/docker/php.ini documents the same values for the container
 *                          (upload_max_filesize = 64M, post_max_size = 64M) and the host
 *                          equivalent is written down in docs/client-guide/ar/16-limits-and-provider.md.
 *   nginx (container):     client_max_body_size 64m in deploy/docker/nginx.conf.
 *
 * Values are kilobytes, which is the unit Laravel's `max:` rule uses for files.
 */
return [
    /*
     * Documents a client actually sends -- signed contracts, scanned invoices, spreadsheets.
     * Used by the web-form file/image attributes and the editor image upload.
     */
    'max_size_kb' => (int) env('UPLOAD_MAX_SIZE_KB', 65536),

    /*
     * Mail attachments keep a lower ceiling on purpose: the attachment repository reads the
     * whole file into memory and the mailer base64-encodes it again for transport, so a
     * 64 MB attachment would need roughly three times its size in PHP memory. The container's
     * memory_limit is 256M (deploy/docker/php.ini), which leaves room for 20 MB and not for 64.
     */
    'mail_attachment_max_kb' => (int) env('UPLOAD_MAIL_ATTACHMENT_MAX_KB', 20480),

    /*
     * The public web form stays where it was. Its endpoint is anonymous, unthrottled and writes to
     * a publicly served disk, so a large ceiling lets any visitor decide how much disk a submission
     * takes -- `Tests\Feature\Security\WebFormFileUploadTest` refuses a 4 MB submission for exactly
     * that reason. Documents a client sends belong on the authenticated surfaces above, not here.
     */
    'web_form_max_size_kb' => (int) env('UPLOAD_WEB_FORM_MAX_SIZE_KB', 2048),
];
