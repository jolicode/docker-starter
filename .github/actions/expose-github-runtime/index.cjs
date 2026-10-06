// GitHub only gives these variables to JavaScript actions, not to "run" steps:
// export them to the next steps of the job, buildx needs them to reach the
// GitHub Actions cache (type=gha, see infrastructure/docker/docker-compose.ci.yml).
// They are the ones read by buildx, see addGithubToken() in its build/opt.go.
//
// Without ACTIONS_CACHE_SERVICE_V2, buildx uses the legacy cache API (v1), which
// fails with an HTTP 400: it defaults to "true" when the runner does not set it.
//
// Kept dependency-free on purpose: it handles the job token.
const fs = require('node:fs');

const variables = {
    ACTIONS_RUNTIME_TOKEN: process.env.ACTIONS_RUNTIME_TOKEN,
    ACTIONS_RESULTS_URL: process.env.ACTIONS_RESULTS_URL,
    ACTIONS_CACHE_URL: process.env.ACTIONS_CACHE_URL,
    ACTIONS_CACHE_SERVICE_V2: process.env.ACTIONS_CACHE_SERVICE_V2 || 'true',
};

for (const [name, value] of Object.entries(variables)) {
    if (value) {
        fs.appendFileSync(process.env.GITHUB_ENV, `${name}=${value}\n`);
    }
}
