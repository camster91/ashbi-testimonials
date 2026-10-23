# Testimonial management permission boundary

Continue T06 with a small, testable boundary slice. The default capability remains `edit_others_posts`: editors and administrators manage testimonials, while visitors, subscribers and authors cannot. A site may intentionally replace this through `abt_capability`; testimonial management must not grant access to unrelated private product/page titles.

Acceptance:
- Every testimonial admin AJAX handler rejects a valid-nonce caller lacking the management capability and rejects missing/invalid nonces even for administrators.
- Editors retain ordinary review read/save access but cannot change administrator settings. Unauthorized export/settings requests are rejected.
- Public load-more output never includes unpublished review content or reviewer email, including forged status attributes.
- A custom lower-privilege testimonial manager sees public unprotected picker entries and entries they can edit. Other private, draft and password-protected titles are omitted. Administrators retain their picker access.
- Non-testimonial post IDs cannot be edited or trashed through review handlers.

Use real users and WordPress handlers in a disposable integration fixture. Intercept only the response termination boundary; assertions inspect JSON and database state. Add the fixture to minimum/latest WordPress CI. This does not claim complete object-capability coverage, multisite role coverage, concurrency/abuse testing or browser QA. No new role, access grant or production configuration change is included.
