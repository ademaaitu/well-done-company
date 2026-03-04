import { useEffect, useMemo, useState } from "react";
import {
  fetchPrototypeConfig,
  startSession,
  submitPrototypeAttempt,
  trackSessionEvent,
} from "./api";

function localFallbackSessionId() {
  if (window.crypto?.randomUUID) {
    return window.crypto.randomUUID();
  }

  return `session-${Date.now()}`;
}

function App() {
  const [view, setView] = useState("landing");
  const [config, setConfig] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  const [userName, setUserName] = useState("");
  const [sessionId, setSessionId] = useState("");

  const [scenarioNodeId, setScenarioNodeId] = useState("s1");
  const [scenarioPath, setScenarioPath] = useState([]);
  const [scenarioFinal, setScenarioFinal] = useState(null);

  const [stressStarted, setStressStarted] = useState(false);
  const [stressTimeLeft, setStressTimeLeft] = useState(240);
  const [stressAnswers, setStressAnswers] = useState({});

  const [quizIndex, setQuizIndex] = useState(0);
  const [quizAnswers, setQuizAnswers] = useState({});

  const [result, setResult] = useState(null);

  useEffect(() => {
    void loadConfig();
  }, []);

  useEffect(() => {
    if (view !== "stress-run" || !stressStarted || stressTimeLeft <= 0) {
      return;
    }

    const timer = setInterval(() => {
      setStressTimeLeft((previous) => {
        if (previous <= 1) {
          clearInterval(timer);
          return 0;
        }

        return previous - 1;
      });
    }, 1000);

    return () => clearInterval(timer);
  }, [view, stressStarted, stressTimeLeft]);

  const nodeMap = useMemo(() => {
    const nodes = config?.scenario?.nodes || [];
    return Object.fromEntries(nodes.map((node) => [node.id, node]));
  }, [config]);

  const finalMap = useMemo(() => {
    const finals = config?.scenario?.finals || [];
    return Object.fromEntries(finals.map((item) => [item.id, item]));
  }, [config]);

  const currentNode = nodeMap[scenarioNodeId] || null;
  const quizQuestions = config?.quiz?.questions || [];
  const currentQuizQuestion = quizQuestions[quizIndex] || null;

  const totalScreens = 13;

  const currentScreen = useMemo(() => {
    if (view === "landing") return 1;
    if (view === "intro") return 2;
    if (view === "scenario") return Math.min(3 + scenarioPath.length, 5);
    if (view === "scenario-final") return 6;
    if (view === "stress-brief") return 7;
    if (view === "stress-run") return 8;
    if (view === "quiz") return Math.min(9 + quizIndex, 13);
    if (view === "result") return 13;
    return 1;
  }, [view, scenarioPath.length, quizIndex]);

  const progressPercent = Math.round((currentScreen / totalScreens) * 100);

  const landingBenefits = [
    "Модуль поможет понять риски и опасные зоны во время землетрясения.",
    "Вы научитесь правильным действиям в момент толчков.",
    "Сможете проверить свою готовность через практические сценарии.",
    "Подготовитесь так, чтобы сохранять спокойствие и уверенность.",
  ];

  const landingIncludes = [
    "Интерактивные сценарии с ветвлениями действий",
    "Практические советы и подсказки",
    "Визуализация безопасных мест и маршрутов",
    "Обратная связь после каждого сценария",
    "Итоговая оценка вашей готовности",
  ];

  async function loadConfig() {
    setLoading(true);
    setError("");

    try {
      const payload = await fetchPrototypeConfig();
      setConfig(payload);
      setStressTimeLeft(payload?.stress_case?.timer_seconds || 240);
    } catch (requestError) {
      setError(requestError.message || "Не удалось загрузить модуль.");
    } finally {
      setLoading(false);
    }
  }

  async function beginModule() {
    if (!userName.trim()) {
      setError("Введите имя, чтобы начать.");
      return;
    }

    setLoading(true);
    setError("");

    try {
      const session = await startSession(userName.trim(), null, "mall_staff");
      const createdSessionId = session?.session_id || localFallbackSessionId();
      setSessionId(createdSessionId);
      setScenarioNodeId("s1");
      setScenarioPath([]);
      setScenarioFinal(null);
      setStressStarted(false);
      setStressTimeLeft(config?.stress_case?.timer_seconds || 240);
      setStressAnswers({});
      setQuizIndex(0);
      setQuizAnswers({});
      setResult(null);
      setView("scenario");

      void trackSessionEvent(createdSessionId, "prototype_started", {
        audience: "mall_staff",
      });
    } catch (requestError) {
      setError(requestError.message || "Не удалось запустить модуль.");
    } finally {
      setLoading(false);
    }
  }

  function chooseScenarioOption(option) {
    if (!currentNode) return;

    const nextPath = [
      ...scenarioPath,
      {
        node_id: currentNode.id,
        option_id: option.id,
        option_label: option.label,
      },
    ];
    setScenarioPath(nextPath);

    if (String(option.next || "").startsWith("final")) {
      const targetFinal = finalMap[option.next] || null;
      setScenarioFinal(targetFinal);
      setView("scenario-final");

      if (sessionId && targetFinal) {
        void trackSessionEvent(sessionId, "scenario_finished", {
          final_id: targetFinal.id,
          risk_level: targetFinal.risk_level,
        });
      }

      return;
    }

    setScenarioNodeId(option.next);
  }

  function startStressCase() {
    setStressStarted(true);
    setStressTimeLeft(config?.stress_case?.timer_seconds || 240);

    if (sessionId) {
      void trackSessionEvent(sessionId, "stress_case_started", {
        timer_seconds: config?.stress_case?.timer_seconds || 240,
      });
    }
  }

  function setStressAnswer(questionId, optionId) {
    setStressAnswers((previous) => ({
      ...previous,
      [questionId]: optionId,
    }));
  }

  function formatTime(seconds) {
    const mins = Math.floor(seconds / 60)
      .toString()
      .padStart(2, "0");
    const secs = (seconds % 60).toString().padStart(2, "0");
    return `${mins}:${secs}`;
  }

  function continueToQuiz() {
    const stressQuestions = config?.stress_case?.questions || [];
    const answered = stressQuestions.every((item) => stressAnswers[item.id]);

    if (!answered && stressTimeLeft > 0) {
      setError(
        "Ответьте на все пункты стресс-кейса или дождитесь окончания таймера.",
      );
      return;
    }

    setError("");
    setView("quiz");

    if (sessionId) {
      void trackSessionEvent(sessionId, "stress_case_submitted", {
        answers_count: Object.keys(stressAnswers).length,
        timer_left: stressTimeLeft,
      });
    }
  }

  function chooseQuizOption(optionId) {
    if (!currentQuizQuestion) return;

    setQuizAnswers((previous) => ({
      ...previous,
      [currentQuizQuestion.id]: optionId,
    }));
  }

  async function nextQuizStep() {
    if (!currentQuizQuestion) return;

    if (!quizAnswers[currentQuizQuestion.id]) {
      setError("Выберите вариант ответа.");
      return;
    }

    setError("");

    if (quizIndex < 4) {
      setQuizIndex((previous) => previous + 1);
      return;
    }

    await finalizeModule();
  }

  async function finalizeModule() {
    if (!sessionId || !scenarioFinal) {
      setError("Сессия неполная. Начните модуль заново.");
      return;
    }

    setLoading(true);
    setError("");

    try {
      const quizPayload = (config?.quiz?.questions || []).map((question) => ({
        question_id: question.id,
        selected_option: quizAnswers[question.id] || null,
      }));

      const submission = await submitPrototypeAttempt(
        sessionId,
        userName.trim(),
        scenarioPath,
        scenarioFinal.id,
        stressAnswers,
        quizPayload,
      );

      setResult(submission);
      setView("result");

      void trackSessionEvent(sessionId, "result_viewed", {
        overall_preparedness_percent: submission.overall_preparedness_percent,
        risk_category: submission.risk_category,
      });
    } catch (requestError) {
      setError(requestError.message || "Не удалось завершить модуль.");
    } finally {
      setLoading(false);
    }
  }

  function restart() {
    setView("landing");
    setScenarioNodeId("s1");
    setScenarioPath([]);
    setScenarioFinal(null);
    setStressStarted(false);
    setStressTimeLeft(config?.stress_case?.timer_seconds || 240);
    setStressAnswers({});
    setQuizIndex(0);
    setQuizAnswers({});
    setResult(null);
    setError("");
  }

  return (
    <main className="layout">
      <section className="container premium-surface fade-in">
        <header className="header">
          <h1>
            {config?.title || "Проверь себя: готов ли ты к землетрясению?"}
          </h1>
          <p className="subtitle">Целевая аудитория: сотрудники ТРЦ</p>
          <div className="progress-track">
            <div
              className="progress-fill animated"
              style={{ width: `${progressPercent}%` }}
            />
          </div>
          <small>
            Экран {currentScreen}/{totalScreens}
          </small>
        </header>

        {error && <p className="error">{error}</p>}

        {view === "landing" && (
          <section className="panel smooth landing">
            <div className="landing-hero">
              <div className="landing-copy">
                <h2>
                  Готов ли ты к землетрясению? Узнай и подготовься заранее.
                </h2>
                <p>
                  Интерактивный обучающий модуль поможет понять реальные угрозы,
                  проверить свои навыки и подготовиться к чрезвычайным ситуациям
                  без стресса.
                </p>
              </div>
            </div>

            <article className="landing-block">
              <h3>Почему это важно</h3>
              <ul className="landing-list">
                {landingBenefits.map((item) => (
                  <li key={item}>{item}</li>
                ))}
              </ul>
            </article>

            <article className="landing-block">
              <h3>Что вас ждёт</h3>
              <ul className="landing-list">
                {landingIncludes.map((item) => (
                  <li key={item}>{item}</li>
                ))}
              </ul>
            </article>

            <article className="landing-block">
              <h3>Как это работает</h3>
              <p>
                Просто пройдите модуль: выбирайте действия, наблюдайте
                последствия, анализируйте, что можно улучшить. Не требует
                специальных знаний — всё построено на реальных ситуациях.
              </p>
            </article>

            <section className="cta-strip">
              <div>
                <h3>Начать обучение сейчас</h3>
                <p>
                  Модуль займёт всего 5–10 минут, но знания останутся на всю
                  жизнь.
                </p>
              </div>
              <div className="cta-controls">
                <button className="primary" onClick={() => setView("intro")}>
                  Перейти к модулю
                </button>
              </div>
            </section>

            <div className="row nav">
              <button className="secondary" type="button" disabled>
                Ознакомительный экран
              </button>
            </div>
          </section>
        )}

        {view === "intro" && (
          <section className="panel smooth">
            <h2>Запуск модуля: «Торговый центр. Землетрясение»</h2>
            <p>
              Это практический интерактивный модуль для сотрудников ТРЦ. На
              следующем шаге начнётся сценарий с ветвлением, далее — стресс-кейс
              и тест.
            </p>

            <div className="highlights-grid">
              <article className="highlight-card">
                <h3>Формат</h3>
                <p>Реалистичные ситуации и выбор решений с последствиями.</p>
              </article>
              <article className="highlight-card">
                <h3>Длительность</h3>
                <p>Около 5–10 минут.</p>
              </article>
              <article className="highlight-card">
                <h3>Результат</h3>
                <p>Персональная оценка и рекомендации по улучшению.</p>
              </article>
            </div>

            <div className="cta-strip">
              <div>
                <h3>Введите имя сотрудника</h3>
                <p>Имя будет использовано в персональном результате.</p>
              </div>
              <div className="cta-controls">
                <input
                  className="input"
                  value={userName}
                  onChange={(event) => setUserName(event.target.value)}
                  placeholder="Введите имя сотрудника"
                />
                <button
                  className="primary"
                  onClick={beginModule}
                  disabled={loading}
                >
                  {loading ? "Запуск..." : "Начать модуль"}
                </button>
              </div>
            </div>

            <div className="row nav">
              <button className="secondary" onClick={() => setView("landing")}>
                Назад
              </button>
            </div>
          </section>
        )}

        {view === "scenario" && currentNode && (
          <section className="panel smooth scenario">
            <h2>{currentNode.title}</h2>
            <p>{currentNode.text}</p>
            <div className="option-list">
              {(currentNode.options || []).map((option) => (
                <button
                  key={option.id}
                  className="option"
                  onClick={() => chooseScenarioOption(option)}
                >
                  <strong>{option.id}</strong> — {option.label}
                </button>
              ))}
            </div>
          </section>
        )}

        {view === "scenario-final" && scenarioFinal && (
          <section className="panel smooth result">
            <h2>{scenarioFinal.label}</h2>
            <p>{scenarioFinal.outcome}</p>
            <p>
              <strong>{scenarioFinal.result}</strong>
            </p>
            <div className="row nav">
              <button
                className="primary"
                onClick={() => setView("stress-brief")}
              >
                Перейти к стресс-кейсу
              </button>
            </div>
          </section>
        )}

        {view === "stress-brief" && (
          <section className="panel smooth micro">
            <h2>{config?.stress_case?.title}</h2>
            <ul className="resource-list">
              {(config?.stress_case?.context || []).map((line) => (
                <li key={line}>{line}</li>
              ))}
            </ul>
            <div className="row nav">
              <button className="primary" onClick={() => setView("scenario")}>
                Назад
              </button>
              <button className="primary" onClick={() => setView("stress-run")}>
                Далее
              </button>
            </div>
          </section>
        )}

        {view === "stress-run" && (
          <section className="panel smooth scenario">
            <h2>Стресс-кейс: решение за 4 минуты</h2>
            {!stressStarted ? (
              <div>
                <p>
                  Нажмите «Поехали», после чего запустится таймер и откроются
                  вопросы.
                </p>
                <button className="primary" onClick={startStressCase}>
                  Поехали
                </button>
              </div>
            ) : (
              <>
                <p className={`timer ${stressTimeLeft <= 30 ? "danger" : ""}`}>
                  Таймер: {formatTime(stressTimeLeft)}
                </p>
                {(config?.stress_case?.questions || []).map((question) => (
                  <article key={question.id} className="panel embedded">
                    <h3>{question.text}</h3>
                    <div className="option-list">
                      {(question.options || []).map((option) => (
                        <button
                          key={option.id}
                          className={`option ${stressAnswers[question.id] === option.id ? "selected" : ""}`}
                          onClick={() =>
                            setStressAnswer(question.id, option.id)
                          }
                        >
                          {option.label}
                        </button>
                      ))}
                    </div>
                  </article>
                ))}
                <div className="row nav">
                  <button className="primary" onClick={continueToQuiz}>
                    К тесту
                  </button>
                </div>
              </>
            )}
          </section>
        )}

        {view === "quiz" && currentQuizQuestion && (
          <section className="panel smooth scenario">
            <h2>{config?.quiz?.title}</h2>
            <p className="question-meta">Вопрос {quizIndex + 1}/5</p>
            <h3>{currentQuizQuestion.text}</h3>
            <div className="option-list">
              {(currentQuizQuestion.options || []).map((option) => (
                <button
                  key={option.id}
                  className={`option ${quizAnswers[currentQuizQuestion.id] === option.id ? "selected" : ""}`}
                  onClick={() => chooseQuizOption(option.id)}
                >
                  <strong>{option.id}</strong> — {option.label}
                </button>
              ))}
            </div>
            <div className="row nav">
              <button
                className="primary"
                onClick={nextQuizStep}
                disabled={loading}
              >
                {quizIndex === 4 ? "Завершить модуль" : "Следующий вопрос"}
              </button>
            </div>
          </section>
        )}

        {view === "result" && result && (
          <section className="panel smooth result">
            <h2>Персональный итог: {result.user_name}</h2>
            <p>
              <strong>Готовность:</strong> {result.overall_preparedness_percent}
              %
            </p>
            <p>
              <strong>Сценарий (ветвление):</strong> {result.scenario_score}%
            </p>
            <p>
              <strong>Стресс-кейс:</strong> {result.stress_score}%
            </p>
            <p>
              <strong>Тест:</strong> {result.quiz_score}% (
              {result.quiz_correct_count}/5)
            </p>
            <p className="risk-badge risk-moderate">
              Категория риска: {result.risk_category}
            </p>
            <p>{result.behavioral_analysis}</p>
            <p>
              <strong>Рекомендация:</strong> {result.recommendation}
            </p>

            <h3>Персональный чек-лист</h3>
            <div className="swipe-cards">
              {(result.personalized_checklist || []).map((item) => (
                <article key={item} className="swipe-card">
                  {item}
                </article>
              ))}
            </div>

            <div className="row nav">
              <button className="secondary" onClick={restart}>
                Пройти заново
              </button>
              <button
                className="secondary"
                onClick={() => {
                  window.open(
                    "https://t.me/share/url?url=" +
                      encodeURIComponent(window.location.href),
                    "_blank",
                  );
                }}
              >
                Поделиться
              </button>
            </div>
          </section>
        )}
      </section>
    </main>
  );
}

export default App;
