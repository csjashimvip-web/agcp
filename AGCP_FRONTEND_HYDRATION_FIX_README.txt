AGCP Frontend Hydration Hotfix

Cause found in the supplied project/logs:
- Clicking Sign in performs a native GET /login? instead of the React handler.
- Next.js development HMR requests return 404 behind Nginx.
- Therefore the login form is not reliably hydrated; the browser clears the form and reloads it.

Apply:
1. Put fix-agcp-frontend-hydration.ps1 in C:\Projects\agcp
2. Open PowerShell in C:\Projects\agcp
3. Run:
   Set-ExecutionPolicy -Scope Process Bypass
   .\fix-agcp-frontend-hydration.ps1
4. Wait 20-30 seconds.
5. Open a new Incognito window:
   http://localhost:8080/login

The script:
- Backs up Nginx and dev Compose configuration.
- Adds WebSocket/HMR proxy headers to Nginx.
- Runs Next.js dev using the webpack dev server.
- Clears stale .next cache.
- Rebuilds only frontend and nginx.
- Does not remove MySQL or Redis volumes.
