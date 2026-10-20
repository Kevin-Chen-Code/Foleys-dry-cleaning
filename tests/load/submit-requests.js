import http from 'k6/http';
import exec from 'k6/execution';
import { check, fail } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';

const manifestPath = __ENV.FOLEYS_LOAD_MANIFEST;
if (!manifestPath) throw new Error('Run through php tests/load/run.php; a generated manifest is required.');
const plan = JSON.parse(open(manifestPath));
if (plan.mode !== 'foleys-isolated-local-v1' || !/^http:\/\/127\.0\.0\.1:[1-9][0-9]{3,4}$/.test(plan.baseUrl)
    || !/^[a-f0-9]{64}$/.test(plan.token) || !/^lt[0-9]{14}[a-f0-9]{8}$/.test(plan.runId)) {
  throw new Error('Refusing a production, external or unrecognised target.');
}
const attempts = new Counter('submission_attempts');
const accepted = new Counter('submissions_accepted');
const success = new Rate('submission_success');
const duration = new Trend('submission_duration', true);
const headers = { 'X-Load-Test-Token': plan.token };
export const options = {
  scenarios: {
    submissions: {
      executor: 'constant-arrival-rate', rate: plan.requests, timeUnit: `${plan.duration}s`,
      // Allocate enough clients for the proposed 3-second response-time budget.
      duration: `${plan.duration}s`, preAllocatedVUs: Math.min(1000, plan.requests, Math.max(8, Math.ceil(plan.requests / plan.duration * 3))),
      maxVUs: Math.min(1000, Math.max(80, plan.requests)), gracefulStop: '60s',
    },
  },
  thresholds: {
    http_req_failed: ['rate==0'], checks: ['rate==1'], submission_success: ['rate==1'],
    submission_duration: ['p(95)<3000'], dropped_iterations: ['count==0'],
    submission_attempts: [`count==${plan.requests}`], submissions_accepted: [`count==${plan.requests}`],
  },
};

export function setup() {
  const response = http.get(`${plan.baseUrl}/__loadtest/health`, { headers, redirects: 0 });
  if (response.status !== 200 || response.headers['X-Foleys-Load-Test'] !== plan.runId
      || response.json('mode') !== plan.mode || response.json('runId') !== plan.runId
      || response.json('emails') !== 'non-sending') {
    throw new Error('Target did not prove that it is the matching isolated, non-sending test server.');
  }
}

export default function () {
  const id = exec.scenario.iterationInTest;
  // Arrival timers can schedule an iteration on the end boundary; never submit an extra order.
  if (id >= plan.requests) return;
  attempts.add(1);
  accepted.add(0);
  const start = Date.now();
  let ok = false;
  try {
    http.cookieJar().clear(plan.baseUrl);
    const form = http.get(`${plan.baseUrl}/request`, { headers, redirects: 0, tags: { name: 'GET request form' } });
    const csrf = form.html().find('input[name="_csrfToken"]').attr('value');
    if (!check(form, { 'form and CSRF token available': r => r.status === 200 && !!csrf && r.headers['X-Foleys-Load-Test'] === plan.runId })) {
      fail('Request form or CSRF token unavailable.');
    }
    const profile = plan.profiles[id % plan.profiles.length];
    const payload = {
      _csrfToken: csrf, barrister_name: `Load Test ${plan.runId} ${id}`,
      email: `${plan.runId}-${id}@example.com`, notes: plan.runId,
    };
    profile.items.forEach(([itemId, quantity], index) => {
      payload[`items[${index}][service_item_id]`] = String(itemId);
      payload[`items[${index}][quantity]`] = String(quantity);
    });
    const response = http.post(`${plan.baseUrl}/request`, payload, { headers, redirects: 0, timeout: '30s', tags: { name: 'POST request' } });
    if (!check(response, {
      'save redirects to receipt': r => r.status === 302
        && ['/request/received', `${plan.baseUrl}/request/received`].includes(r.headers.Location)
        && r.headers['X-Foleys-Load-Test'] === plan.runId,
    })) { fail(`Submission ${id} did not confirm a save (HTTP ${response.status}; redirect ${response.headers.Location}).`); }
    const receipt = http.get(`${plan.baseUrl}/request/received`, { headers, redirects: 0, tags: { name: 'GET receipt' } });
    ok = check(receipt, {
      'receipt confirms save and expected discounted price': r => r.status === 200
        && r.body.includes('Your dry-cleaning collection request has been saved.')
        && r.body.includes(`$${(profile.total / 100).toFixed(2)}`)
        && !r.body.includes('could not be sent'),
    });
    if (ok) accepted.add(1);
  } finally {
    success.add(ok);
    duration.add(Date.now() - start);
  }
}

export function handleSummary(data) {
  const value = (name, key) => data.metrics[name]?.values[key] ?? 0;
  const text = [
    `Run: ${plan.runId}`,
    `Attempted: ${value('submission_attempts', 'count')}; confirmed: ${value('submissions_accepted', 'count')}`,
    `Dropped scheduled submissions: ${value('dropped_iterations', 'count')}`,
    `Submission p95: ${value('submission_duration', 'p(95)').toFixed(2)} ms; max: ${value('submission_duration', 'max').toFixed(2)} ms`,
    'Full metrics: ' + plan.directory + '/k6-summary.json', '',
  ].join('\n');
  return { stdout: text, [`${plan.directory}/k6-summary.json`]: JSON.stringify(data, null, 2) };
}
