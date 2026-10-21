# SmartDoc

## Chatbot setup

The chatbot uses Gemini to converse naturally, understand paraphrases and multilingual/Taglish questions, and find semantically relevant answers among active, administrator-approved FAQs and active document types. Document type names, fees, processing days, and requirements come from the document type tables; the fee is paid at pickup, and SmartDoc does not process online payments. Other school-specific policies must be supported by an active approved FAQ. The assistant may explain or translate grounded details, but must not guess missing policies. Greetings and small talk receive normal replies; a missing answer gets a safe conversational response rather than an availability error.

1. Configure `GEMINI_API_KEY` in the local server environment. Keep this key server-side; never add it to JavaScript or commit it.
2. Optionally set `GEMINI_MODEL` and `GEMINI_TIMEOUT`. The default model is `gemini-3.8-flash`; the request timeout defaults to 15 seconds and transient connection/rate-limit/server errors are retried once.
3. Apply pending migrations with `php artisan migrate` after confirming the target database and backup. This includes the per-user chatbot message history table.
4. Sign in as an administrator, open **Manage FAQs**, add the answers confirmed by the responsible school office, approve them, then activate them.
5. Students can open the assistant from their dashboard or sidebar. Clickable suggestions are generated from active, approved FAQs, with admin-selected suggestions shown first.

Chat history is saved to the signed-in user's account and is restored after refresh or sign-out/sign-in. Only that account can view or clear its history using the **Clear history** button. The current message and up to four previous exchanges are sent to Google Gemini for context. SmartDoc stores non-identifying topic metadata for unanswered SmartDoc questions; greetings and unrelated small talk are not added to inquiry analytics. Repeated unanswered SmartDoc topics (at least 3 inquiries in 30 days) appear as admin-only FAQ recommendations. Admins can create a draft from a recommendation, but must add an office-verified answer, approve, and activate it before students can receive it. Personal request status is not disclosed by the chatbot; students should use the authenticated **My Requests** page.

## Document request workflows

- Students use **My requests & history** to submit a document request, upload the configured PDF/JPG/PNG requirements, follow status and stage updates, and review the activity timeline. Requirement files are stored on Laravel's private local disk and can only be downloaded by the request owner or staff/admin.
- Requests are pickup-only. Applicable fees are shown from the document type record and are paid at the Registrar office when the document is collected; SmartDoc has no payment processing.
- Staff and admins use **Request management** to review each upload, optionally request an AI legibility/completeness recommendation, then make the final verification decision themselves. If a file needs correction, the student can replace it and staff review it again; requests cannot be rejected through the workflow. Staff/admins start processing only after all required files are verified and advance work through its stages. Students confirm pickup to complete a request. **Request history** provides filtered records and CSV export.
- The optional AI requirement review sends the selected private PDF/JPG/PNG file to Google Gemini. It cannot determine authenticity or make verification/status decisions. Configure and disclose this data transfer before enabling it for real student records.
- Admins manage role-specific accounts, departments, offices, positions, courses, year levels, sections, announcements, document types, and each document type's requirements from the admin navigation. User Management contains indented links to the profile-option catalogs. Student selectors enforce the Course → Year → Section relationship; staff/admin accounts require department, office, and role-appropriate position selections. Each catalog, including offices and student profile options, supports review-confirmed CSV/XLSX bulk import.
- Records are archived rather than deleted; each management page has current/archived views, single-record actions, and bulk selection (up to 100 records per action). The central **Archive** page switches between record categories and provides restore actions. Archiving a document type deactivates it; restored types stay inactive until explicitly reactivated. Processing-day estimates use the median weekday duration of up to 100 completed requests for that document type, are shown after at least three samples, and remain an optional admin suggestion.
- Deactivating a user records the inactive date. A daily scheduled command archives deactivated accounts after three years, unless they still have an open document request. Admins can also archive graduated students manually. Archived users cannot sign in; restoring an account removes its archive marker while keeping its prior active/deactivated status. The `2026_10_17_000006_add_archival_support` migration backfills inactive dates for existing deactivated accounts using their last `updated_at` timestamp.
- Account Settings displays the user's name and read-only role-specific details (student course/year/section, or staff/admin department/office/position). The profile icon itself opens the image picker; the selected photo can be previewed before saving. Only photo, email, and password can be changed there.
- To run the three-year auto-archive job in production, configure Laravel's scheduler. On Windows Task Scheduler, run `php artisan schedule:run` every minute from the project directory; the scheduled command is `php artisan smartdoc:archive-inactive-users`. You can also run that command directly to execute the archive check immediately.
- A notification bell lists recent request, announcement, and document-type updates. Admin activity logs include successful logins, readable record names, and human-readable change summaries; role tabs filter Admin, Staff, and Student activity while **All** also includes system events. The CSV export uses the same filters and the `User`, `Activity Details`, and `Date & Time` columns. Admin management pages offer bulk import using a downloadable Excel-compatible CSV template or an `.xlsx`/`.csv` upload. Imports show validated rows and require an explicit review confirmation before records are saved. Users can change their email, profile photo, and password in Account settings; email/password changes require the current password.
- Self-service password reset uses Laravel's password broker and sends a one-time reset link—not a plaintext password—to the account email. The **Reset password** button in Account Settings sends the link to the signed-in user's registered email; it is throttled and does not accept a different recipient. Configure a working mailer in the server's `.env`; do not commit SMTP credentials. For Gmail SMTP, use `MAIL_MAILER=smtp`, `MAIL_HOST=smtp.gmail.com`, `MAIL_PORT=587`, `MAIL_USERNAME` set to the sending Gmail address, `MAIL_PASSWORD` set to its Google App Password, `MAIL_ENCRYPTION=tls`, `MAIL_FROM_ADDRESS` set to that Gmail address, and `MAIL_FROM_NAME` set to `SmartDoc`. Google requires 2-Step Verification before creating an App Password. Set `APP_URL` to the site's real HTTPS address so reset links work for users, then run `php artisan config:clear` after changing mail settings. Confirm delivery through the Forgot Password page before relying on it. Reset delivery is not functional until valid mail settings and user email addresses are configured; the user chooses their new password on the secure reset page.
- The portal operations migration `2026_10_17_000005_add_portal_operations_tables` and archival migration `2026_10_17_000006_add_archival_support` have been applied to the local `smartdoc` database. For another environment, confirm the configured database and backup before running `php artisan migrate`.

Run the focused chatbot assistant tests with `php artisan test tests/Unit/GeminiChatAssistantTest.php tests/Feature/ChatbotTest.php`.

## Framework

This project uses Laravel 10 and Blade. The chatbot uses Laravel's built-in HTTP client and requires no additional Composer or npm package.

For more information about Laravel, see the [Laravel documentation](https://laravel.com/docs).

## Gemini references

- [Gemini API models](https://ai.google.dev/gemini-api/docs/models)
- [Gemini structured outputs](https://ai.google.dev/gemini-api/docs/structured-output)

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
