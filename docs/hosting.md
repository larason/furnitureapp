**Hosting decision for Phase 18.3:** SL Furnitures will use a self-managed VPS running Coolify. The Laravel API, customer Next.js website, admin Next.js dashboard, and Laravel Reverb will be deployed through Coolify on the same VPS initially.

Operational procedures — schema changes, feature flags, secrets, and test commands — are in
`docs/operations.md`. This file records hosting decisions only.

Proceed with local Reverb integration and the remaining real WebSocket smoke test.

For production readiness:

1. Design a Coolify-compatible deployment with a separate long-running Reverb service/process and supervised Laravel queue workers.
2. Reuse the same Laravel backend codebase and matching Reverb credentials.
3. Prefer Coolify's existing Traefik reverse proxy, with HTTPS/WSS termination.
4. Plan for a dedicated public WebSocket hostname, but do not invent or configure a production domain yet.
5. Keep Reverb's internal listening port private. Do not publicly expose port 8080.
6. Ensure correct private Docker networking between services.
7. Preserve Clerk JWT authorization for `/broadcasting/auth`.
8. Allow only exact approved admin frontend origins.
9. Keep Reverb scaling disabled for the initial single-server deployment.
10. Document the required environment variables, deployment process, worker supervision, restart behavior, logging, and smoke-test procedure.

Do not deploy services, change DNS/firewall rules, or configure production secrets without notifying me and obtaining approval.

Do not activate staff notifications in the customer-facing Next.js or Flutter applications.

Complete the local two-user Reverb smoke test when suitable test identities are available, and report actual results before changing Phase 18.3 from PARTIAL to PASS.