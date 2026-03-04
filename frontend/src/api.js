const API_BASE_URL =
  import.meta.env.VITE_API_BASE_URL || "http://localhost:8000";
const API_TOKEN = import.meta.env.VITE_API_TOKEN || "dev-earthquake-token";

function authHeaders() {
  return {
    "Content-Type": "application/json",
    Authorization: `Bearer ${API_TOKEN}`,
  };
}

async function handleJsonResponse(response) {
  let payload;

  try {
    payload = await response.json();
  } catch {
    payload = null;
  }

  if (!response.ok) {
    const message =
      payload?.message || `Request failed with status ${response.status}`;
    throw new Error(message);
  }

  return payload;
}

async function request(path, options = {}) {
  const response = await fetch(`${API_BASE_URL}${path}`, options);
  return handleJsonResponse(response);
}

export async function fetchModules() {
  return request("/api/modules");
}

export async function startSession(
  userName,
  moduleId = null,
  stressContext = null,
) {
  return request("/api/session/start", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      user_name: userName,
      module_id: moduleId,
      stress_context: stressContext,
    }),
  });
}

export async function fetchResources() {
  return request("/api/resources");
}

export async function trackSessionEvent(sessionId, eventName, payload = {}) {
  return request("/api/session/events", {
    method: "POST",
    headers: authHeaders(),
    body: JSON.stringify({
      session_id: sessionId,
      event_name: eventName,
      payload,
    }),
  });
}

export async function fetchScenarios(moduleId) {
  return request(`/api/modules/${moduleId}/scenarios`);
}

export async function fetchNextScenario(
  moduleId,
  stressContext,
  currentBranchingId,
  selectedOption,
) {
  const params = new URLSearchParams();
  params.set("stress_context", stressContext);

  if (currentBranchingId) {
    params.set("current_branching_id", currentBranchingId);
  }

  if (selectedOption) {
    params.set("selected_option", selectedOption);
  }

  return request(
    `/api/modules/${moduleId}/scenarios/next?${params.toString()}`,
  );
}

export async function submitModuleAnswers(
  moduleId,
  userName,
  stressContext,
  sessionId,
  answers,
) {
  return request(`/api/modules/${moduleId}/submit`, {
    method: "POST",
    headers: authHeaders(),
    body: JSON.stringify({
      user_name: userName,
      stress_context: stressContext,
      session_id: sessionId,
      answers: answers.map((entry) => ({
        scenario_id: Number(entry.scenario_id),
        selected_option: entry.selected_option,
        response_time_ms: Number(entry.response_time_ms || 0),
        retries: Number(entry.retries || 0),
        wrong_explanation_shown: Boolean(entry.wrong_explanation_shown),
      })),
    }),
  });
}

export async function fetchUserResults(userName) {
  return request(`/api/results/${encodeURIComponent(userName)}`, {
    headers: {
      Authorization: `Bearer ${API_TOKEN}`,
    },
  });
}

export async function fetchPrototypeConfig() {
  return request("/api/prototype/config");
}

export async function submitPrototypeAttempt(
  sessionId,
  userName,
  scenarioPath,
  scenarioFinalId,
  stressAnswers,
  quizAnswers,
) {
  return request("/api/prototype/submit", {
    method: "POST",
    headers: authHeaders(),
    body: JSON.stringify({
      session_id: sessionId,
      user_name: userName,
      scenario_path: scenarioPath,
      scenario_final_id: scenarioFinalId,
      stress_answers: stressAnswers,
      quiz_answers: quizAnswers,
    }),
  });
}
