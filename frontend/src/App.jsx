import { useEffect, useMemo, useState } from "react";
import {
  fetchPrototypeConfig,
  startSession,
  submitPrototypeAttempt,
  trackSessionEvent,
} from "./api";

const ASSET_IMAGES = Object.fromEntries(
  Object.entries(
    import.meta.glob("./assets/*.{png,jpg,jpeg,webp,svg}", {
      eager: true,
      import: "default",
    }),
  ).map(([path, url]) => [path.split("/").pop().toLowerCase(), url]),
);

const SCENARIO_IMAGE_MAP = {
  s1: "situation.png",
  s2A: "situation2A.png",
  s2B: "situation2B.png",
  s3A1: "situation3A1.png",
  s3A2: "situation3A2.png",
  s3B1: "situation3B1.png",
  s3B2: "situation3B2.png",
};

function scenarioImageForNode(nodeId) {
  if (!nodeId) return null;
  const fileName = SCENARIO_IMAGE_MAP[nodeId];
  if (!fileName) return null;
  return ASSET_IMAGES[fileName.toLowerCase()] || null;
}

function finalImageForId(finalId) {
  if (!finalId) return null;
  const extensions = ["png", "jpg", "jpeg", "webp", "svg"];
  for (const ext of extensions) {
    const fileName = `${finalId}.${ext}`;
    const found = ASSET_IMAGES[fileName.toLowerCase()];
    if (found) return found;
  }
  return null;
}

function localFallbackSessionId() {
  if (window.crypto?.randomUUID) {
    return window.crypto.randomUUID();
  }

  return `session-${Date.now()}`;
}

function App() {
  const PERFECT_BONUS = 15;
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
  const [stressAnswers, setStressAnswers] = useState({});
  const [miniGameRound, setMiniGameRound] = useState(0);
  const [miniGameSelections, setMiniGameSelections] = useState([]);
  const [miniGameLocked, setMiniGameLocked] = useState(false);
  const [miniGameFeedback, setMiniGameFeedback] = useState("");
  const [miniMapLoaded, setMiniMapLoaded] = useState(false);
  const [miniScore, setMiniScore] = useState(0);
  const [miniCombo, setMiniCombo] = useState(0);
  const [miniBestCombo, setMiniBestCombo] = useState(0);
  const [miniLastSafe, setMiniLastSafe] = useState(null);
  const [miniRoundTimeLeft, setMiniRoundTimeLeft] = useState(null);
  const [miniTimerShake, setMiniTimerShake] = useState(false);
  const [miniModal, setMiniModal] = useState(null);
  const [miniEffect, setMiniEffect] = useState(null);

  const [quizIndex, setQuizIndex] = useState(0);
  const [quizAnswers, setQuizAnswers] = useState({});

  const [result, setResult] = useState(null);
  const [isScenarioTransitioning, setIsScenarioTransitioning] = useState(false);
  const [scenarioImageLoaded, setScenarioImageLoaded] = useState(false);
  const [finalImageLoaded, setFinalImageLoaded] = useState(false);

  useEffect(() => {
    void loadConfig();
  }, []);

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
  const scenarioImageSrc = scenarioImageForNode(currentNode?.id);
  const finalImageSrc = finalImageForId(scenarioFinal?.id);
  const miniGameRounds = useMemo(
    () => [
      {
        key: "priority",
        title: "Раунд 1 — Куда встать при первых толчках?",
        image: "round1.png",
        buttons: [
          { id: "chandelier", label: "Люстра", top: "28.9%", left: "51.7%" },
          { id: "column", label: "Колонна", top: "44.7%", left: "18.5%" },
          {
            id: "showcase",
            label: "Витрина магазина",
            top: "73.7%",
            left: "79.2%",
          },
          { id: "escalator", label: "Эскалатор", top: "57.9%", left: "65.7%" },
        ],
        correctSpot: "column",
        modalCorrect:
          "Колонны — одни из самых прочных элементов здания.\nРядом с ними меньше риск получить удар падающими предметами.",
        wrongMessages: {
          chandelier: "Подвесные конструкции могут упасть.",
          showcase: "Стекло может разбиться.",
          escalator: "Можно потерять равновесие.",
        },
        correctPoints: 10,
        wrongPoints: -5,
      },
      {
        key: "team_split",
        title: "Раунд 2 — Куда отойти, когда падают предметы?",
        image: "round2.png",
        buttons: [
          {
            id: "banner",
            label: "Рекламный баннер",
            top: "18.4%",
            left: "47.2%",
          },
          { id: "mannequin", label: "Манекен", top: "52.6%", left: "23.6%" },
          {
            id: "open_space",
            label: "Открытое пространство",
            top: "60.5%",
            left: "56.2%",
          },
          { id: "showcase", label: "Витрина", top: "68.4%", left: "85.4%" },
        ],
        correctSpot: "open_space",
        modalCorrect:
          "В открытом месте меньше риск попасть под падающие предметы.",
        wrongMessages: {
          banner: "Баннер может сорваться.",
          mannequin: "Манекен может упасть.",
          showcase: "Стекло может разбиться.",
        },
        correctPoints: 10,
        wrongPoints: -5,
      },
      {
        key: "evacuation",
        title: "Раунд 3 — Как выбраться, когда вокруг толпа и паника?",
        image: "round3.png",
        buttons: [
          { id: "lift", label: "Лифт", top: "28.6%", left: "60.7%" },
          { id: "escalator", label: "Эскалатор", top: "31.6%", left: "73.7%" },
          { id: "staircase", label: "Лестница", top: "30.6%", left: "48.9%" },
          {
            id: "food_table",
            label: "Фудкорт стол",
            top: "84.2%",
            left: "46.1%",
          },
        ],
        correctSpot: "staircase",
        modalCorrect:
          "Лестницы — самый безопасный способ спуститься при эвакуации.",
        wrongMessages: {
          lift: "Лифты могут застрять.",
          escalator: "Эскалатор может остановиться.",
          food_table: "Стол не защищает от падающих предметов.",
        },
        correctPoints: 10,
        wrongPoints: -5,
      },
      {
        key: "responsibility",
        title: "Раунд 4 — Какой выход использовать при эвакуации?",
        image: "round4.png",
        buttons: [
          {
            id: "main_exit",
            label: "Основной выход",
            top: "52.6%",
            left: "47.2%",
          },
          {
            id: "emergency_exit",
            label: "Аварийный выход",
            top: "47.4%",
            left: "83.1%",
          },
          { id: "lift", label: "Лифт", top: "44.7%", left: "11.2%" },
          { id: "parking", label: "Парковка", top: "57.9%", left: "56.2%" },
        ],
        correctSpot: "emergency_exit",
        modalCorrect: "Аварийные выходы предназначены для быстрой эвакуации.",
        wrongMessages: {
          lift: "Опасно при землетрясении.",
          parking: "Не предназначена для эвакуации.",
          main_exit: "Может быть перегружен людьми.",
        },
        correctPoints: 10,
        wrongPoints: -5,
      },
    ],
    [],
  );

  const miniGameCurrentRound = miniGameRounds[miniGameRound] || null;
  const miniGameMapSrc = miniGameCurrentRound
    ? ASSET_IMAGES[miniGameCurrentRound.image] || null
    : null;
  const miniAnsweredCount = miniGameSelections.filter(Boolean).length;
  const miniPerfectRun =
    miniAnsweredCount === miniGameRounds.length &&
    miniGameSelections.every((item) => item?.is_safe);
  const miniBonusScore = miniPerfectRun ? PERFECT_BONUS : 0;
  const miniTotalScore = miniScore + miniBonusScore;

  const totalScreens = 14;

  const currentScreen = useMemo(() => {
    if (view === "landing") return 1;
    if (view === "intro") return 2;
    if (view === "scenario") return Math.min(3 + scenarioPath.length, 5);
    if (view === "scenario-final") return 6;
    if (view === "stress-brief") return 7;
    if (view === "stress-run") return 8;
    if (view === "pre-quiz") return 9;
    if (view === "quiz") return Math.min(10 + quizIndex, 14);
    if (view === "result") return 14;
    return 1;
  }, [view, scenarioPath.length, quizIndex]);

  const progressPercent = Math.round((currentScreen / totalScreens) * 100);

  useEffect(() => {
    setScenarioImageLoaded(false);
  }, [scenarioImageSrc, currentNode?.id]);

  useEffect(() => {
    setFinalImageLoaded(false);
  }, [finalImageSrc, scenarioFinal?.id]);

  useEffect(() => {
    if (miniRoundTimeLeft === null) return;
    if (miniGameLocked) return;
    if (miniRoundTimeLeft <= 0) {
      const currentRound = miniGameRounds[miniGameRound];
      const timeoutPoints = currentRound?.wrongPoints ?? -5;
      setMiniGameLocked(true);
      setMiniGameSelections((previous) => {
        const next = [...previous];
        next[miniGameRound] = {
          round: miniGameRound + 1,
          key: currentRound?.key || "",
          spot_id: null,
          is_safe: false,
        };
        return next;
      });
      setMiniScore((s) => s + timeoutPoints);
      setMiniCombo(0);
      setMiniLastSafe(false);
      setMiniEffect("wrong");
      setMiniModal({
        type: "wrong",
        message: "Вы не успели выбрать безопасную зону. Будьте быстрее!",
        points: timeoutPoints,
      });
      window.setTimeout(() => setMiniEffect(null), 650);
      return;
    }
    if (miniRoundTimeLeft === 10) {
      setMiniTimerShake(true);
      window.setTimeout(() => setMiniTimerShake(false), 500);
    }
    const timerId = window.setTimeout(
      () => setMiniRoundTimeLeft((t) => (t !== null ? t - 1 : null)),
      1000,
    );
    return () => window.clearTimeout(timerId);
  }, [miniRoundTimeLeft, miniGameLocked, miniGameRound, miniGameRounds]);

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
      setStressAnswers({});
      setMiniGameRound(0);
      setMiniGameSelections([]);
      setMiniGameLocked(false);
      setMiniGameFeedback("");
      setMiniMapLoaded(false);
      setMiniScore(0);
      setMiniCombo(0);
      setMiniBestCombo(0);
      setMiniLastSafe(null);
      setMiniRoundTimeLeft(null);
      setMiniTimerShake(false);
      setMiniModal(null);
      setMiniEffect(null);
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
    if (!currentNode || isScenarioTransitioning) return;

    setIsScenarioTransitioning(true);

    const nextPath = [
      ...scenarioPath,
      {
        node_id: currentNode.id,
        option_id: option.id,
        option_label: option.label,
      },
    ];

    window.setTimeout(() => {
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

        setIsScenarioTransitioning(false);
        return;
      }

      setScenarioNodeId(option.next);
      setIsScenarioTransitioning(false);
    }, 280);
  }

  function startStressCase() {
    setStressStarted(true);
    setMiniGameRound(0);
    setMiniGameSelections([]);
    setMiniGameLocked(false);
    setMiniGameFeedback("");
    setStressAnswers({});
    setMiniScore(0);
    setMiniCombo(0);
    setMiniBestCombo(0);
    setMiniLastSafe(null);
    setMiniRoundTimeLeft(30);
    setMiniTimerShake(false);
    setMiniModal(null);
    setMiniEffect(null);
    setMiniMapLoaded(false);

    if (sessionId) {
      void trackSessionEvent(sessionId, "stress_case_started", {
        mode: "safe_spot_game",
      });
    }
  }

  function mapRoundAnswer(roundKey, isSafe) {
    const safeMap = {
      priority: "atrium",
      team_split: "balanced",
      evacuation: "controlled",
      responsibility: "take",
    };

    const unsafeMap = {
      priority: "lift_first",
      team_split: "all_lift",
      evacuation: "full_panic",
      responsibility: "delay",
    };

    return isSafe ? safeMap[roundKey] : unsafeMap[roundKey];
  }

  function chooseMiniGameSpot(spotId) {
    if (!miniGameCurrentRound || miniGameLocked) return;

    const isSafe = miniGameCurrentRound.correctSpot === spotId;
    const roundKey = miniGameCurrentRound.key;
    const points = isSafe
      ? miniGameCurrentRound.correctPoints
      : miniGameCurrentRound.wrongPoints;

    setMiniGameSelections((previous) => {
      const next = [...previous];
      next[miniGameRound] = {
        round: miniGameRound + 1,
        key: roundKey,
        spot_id: spotId,
        is_safe: isSafe,
      };
      return next;
    });

    setStressAnswers((previous) => ({
      ...previous,
      [roundKey]: mapRoundAnswer(roundKey, isSafe),
    }));

    setMiniScore((previous) => previous + points);
    setMiniCombo((previous) => {
      const nextCombo = isSafe ? previous + 1 : 0;
      setMiniBestCombo((best) => Math.max(best, nextCombo));
      return nextCombo;
    });
    setMiniLastSafe(isSafe);
    setMiniRoundTimeLeft(null);
    setMiniGameLocked(true);

    if (isSafe) {
      setMiniEffect("correct");
      setMiniModal({
        type: "correct",
        message: miniGameCurrentRound.modalCorrect,
        points,
      });
    } else {
      setMiniEffect("wrong");
      setMiniModal({
        type: "wrong",
        message:
          miniGameCurrentRound.wrongMessages[spotId] ||
          "Это опасная зона во время толчков.",
        points,
      });
      window.setTimeout(() => setMiniEffect(null), 650);
    }
  }

  function nextMiniGameRound() {
    if (!miniGameCurrentRound) return;

    if (miniGameRound >= miniGameRounds.length - 1) {
      setMiniGameLocked(false);
      setMiniRoundTimeLeft(null);
      setMiniModal(null);
      setMiniEffect(null);
      continueToQuiz();
      return;
    }

    setMiniGameRound((previous) => previous + 1);
    setMiniGameLocked(false);
    setMiniRoundTimeLeft(30);
    setMiniTimerShake(false);
    setMiniModal(null);
    setMiniEffect(null);
    setMiniMapLoaded(false);
  }

  function continueToQuiz() {
    const answeredAllRounds = miniAnsweredCount === miniGameRounds.length;

    if (!answeredAllRounds) {
      setError("Пройдите все раунды мини-игры перед тестом.");
      return;
    }

    setError("");
    setView("pre-quiz");

    if (sessionId) {
      void trackSessionEvent(sessionId, "stress_case_submitted", {
        answers_count: Object.keys(stressAnswers).length,
        safe_choices: miniGameSelections.filter((item) => item?.is_safe).length,
        score: miniScore,
        bonus_score: miniBonusScore,
        total_score: miniTotalScore,
        best_combo: miniBestCombo,
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
    setStressAnswers({});
    setMiniGameRound(0);
    setMiniGameSelections([]);
    setMiniGameLocked(false);
    setMiniGameFeedback("");
    setMiniMapLoaded(false);
    setMiniScore(0);
    setMiniCombo(0);
    setMiniBestCombo(0);
    setMiniLastSafe(null);
    setMiniRoundTimeLeft(null);
    setMiniTimerShake(false);
    setMiniModal(null);
    setMiniEffect(null);
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
            {config?.title ||
              "Действуй правильно: готовность сотрудников ТЦ к землетрясению"}
          </h1>
          <p className="subtitle">Целевая аудитория: сотрудники ТРЦ</p>
          <div className="progress-track">
            <div
              className="progress-fill animated"
              style={{ width: `${progressPercent}%` }}
            />
          </div>
        </header>

        {error && <p className="error">{error}</p>}

        {view === "landing" && (
          <section className="panel smooth landing">
            <div className="landing-hero">
              <div className="landing-copy">
                <h2>Узнай и подготовься заранее.</h2>
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
                <h3>Введите имя и начните обучение</h3>
                <p>
                  Модуль займёт всего 5–10 минут. Имя будет отображено в
                  результате.
                </p>
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
            </section>
          </section>
        )}

        {view === "intro" && (
          <section className="panel smooth">
            <h2>Запуск модуля: «Торговый центр. Землетрясение»</h2>
            <p>
              Это практический интерактивный модуль для сотрудников ТРЦ. На
              следующем шаге начнётся сценарий с ветвлением, далее — мини-игра
              «Найди безопасное место» и тест.
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
            <div className="scenario-visual medium">
              {!scenarioImageLoaded && <div className="image-skeleton" />}
              {scenarioImageSrc ? (
                <img
                  src={scenarioImageSrc}
                  alt={`Иллюстрация: ${currentNode.title}`}
                  className={`scenario-image ${scenarioImageLoaded ? "loaded" : ""}`}
                  onLoad={() => setScenarioImageLoaded(true)}
                />
              ) : (
                <div className="image-fallback">Иллюстрация загружается</div>
              )}
            </div>
            <h2>{currentNode.title}</h2>
            <p>{currentNode.text}</p>
            <div className="option-list">
              {(currentNode.options || []).map((option) => (
                <button
                  key={option.id}
                  className="option"
                  disabled={isScenarioTransitioning}
                  onClick={() => chooseScenarioOption(option)}
                >
                  <strong>{option.id}</strong> — {option.label}
                </button>
              ))}
            </div>
            {isScenarioTransitioning && (
              <div className="next-step-skeleton" aria-hidden="true">
                <div className="next-line" />
                <div className="next-line short" />
              </div>
            )}
          </section>
        )}

        {view === "scenario-final" && scenarioFinal && (
          <section className="panel smooth result">
            <div className="scenario-visual medium">
              {!finalImageLoaded && <div className="image-skeleton" />}
              {finalImageSrc ? (
                <img
                  src={finalImageSrc}
                  alt={`Финал: ${scenarioFinal.label}`}
                  className={`scenario-image ${finalImageLoaded ? "loaded" : ""}`}
                  onLoad={() => setFinalImageLoaded(true)}
                />
              ) : (
                <div className="image-fallback">Иллюстрация финала</div>
              )}
            </div>
            <h2>{scenarioFinal.label}</h2>
            <p>{scenarioFinal.outcome}</p>
            <p>
              <strong>{scenarioFinal.result}</strong>
            </p>
            <div className="row nav">
              <button
                className="primary"
                onClick={() => {
                  startStressCase();
                  setView("stress-run");
                }}
              >
                Перейти к стресс-кейсу
              </button>
            </div>
          </section>
        )}

        {view === "stress-brief" && (
          <section className="panel smooth micro">
            <h2>Мини-игра: «Найди безопасное место»</h2>
            <p>
              На карте ТЦ будут анимации опасностей: падающие витрины,
              трясущиеся люстры и мигающие стрелки. В каждом раунде выберите
              безопасную точку.
            </p>
            <ul className="resource-list">
              <li>✔ колонна</li>
              <li>✔ открытое пространство</li>
              <li>❌ стеклянная витрина</li>
              <li>❌ эскалатор</li>
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
            <h2>Стресс-кейс: «Найди безопасное место»</h2>
            {!stressStarted ? (
              <div>
                <p>
                  Серия раундов. В каждом нужно нажать на безопасную точку. На
                  выбор <strong>30 секунд</strong>.
                </p>
                <div className="row nav">
                  <button className="primary" onClick={startStressCase}>
                    Начать
                  </button>
                </div>
              </div>
            ) : (
              <div
                className={`map-game-panel${
                  miniEffect === "wrong" ? " panel-shake" : ""
                }${miniEffect === "correct" ? " panel-glow" : ""}`}
              >
                {/* Red flash overlay */}
                {miniEffect === "wrong" && <div className="panel-red-flash" />}

                {/* Header */}
                <div className="map-overlay-top">
                  <div className="map-round-header">
                    <div className="map-round-title">
                      <span className="map-round-number">
                        {miniGameRound + 1} / {miniGameRounds.length}
                      </span>
                      <h3 className="map-round-name">
                        {miniGameCurrentRound?.title}
                      </h3>
                    </div>
                    {miniRoundTimeLeft !== null && (
                      <span
                        className={`hud-chip map-timer${
                          miniRoundTimeLeft <= 10 ? " danger" : ""
                        }${miniTimerShake ? " timer-shake" : ""}`}
                      >
                        ⏱ {miniRoundTimeLeft}с
                      </span>
                    )}
                  </div>
                  <div className="mini-hud">
                    <span className="hud-chip">Очки: {miniScore}</span>
                    <span className="hud-chip">Комбо: x{miniCombo}</span>
                    <span className="hud-chip">
                      Лучшее комбо: x{miniBestCombo}
                    </span>
                  </div>
                </div>

                {/* Round image */}
                <div
                  className={`map-wrapper${
                    miniEffect === "correct" ? " map-correct-glow" : ""
                  }`}
                >
                  {!miniMapLoaded && (
                    <div className="image-skeleton map-skeleton" />
                  )}
                  {miniGameMapSrc ? (
                    <img
                      src={miniGameMapSrc}
                      alt={miniGameCurrentRound?.title}
                      className={`map-image ${miniMapLoaded ? "loaded" : ""}`}
                      onLoad={() => setMiniMapLoaded(true)}
                    />
                  ) : (
                    <div className="image-fallback map-fallback">
                      {miniGameCurrentRound?.title}
                    </div>
                  )}

                  {/* Buttons overlaid on image */}
                  {(miniGameCurrentRound?.buttons || []).map((btn) => {
                    const sel = miniGameSelections[miniGameRound];
                    const isSelected = sel?.spot_id === btn.id;
                    const isCorrect =
                      btn.id === miniGameCurrentRound.correctSpot;
                    let cls = "round-choice-btn";
                    if (miniGameLocked && isSelected) {
                      cls += isCorrect ? " correct" : " wrong";
                    } else if (miniGameLocked && isCorrect) {
                      cls += " correct-hint";
                    }
                    return (
                      <button
                        key={btn.id}
                        className={cls}
                        style={{ top: btn.top, left: btn.left }}
                        onClick={() => chooseMiniGameSpot(btn.id)}
                        disabled={miniGameLocked}
                      >
                        {btn.label}
                      </button>
                    );
                  })}
                </div>

                {/* Next round button — always rendered to keep stable height */}
                <div className="map-controls">
                  <div className="row nav">
                    {miniGameLocked && !miniModal && (
                      <button className="primary" onClick={nextMiniGameRound}>
                        {miniGameRound === miniGameRounds.length - 1
                          ? "Завершить стресс-кейс"
                          : "Следующий раунд"}
                      </button>
                    )}
                  </div>
                </div>

                {/* Modal overlay */}
                {miniModal && (
                  <div className="round-modal-overlay">
                    <div className={`round-modal ${miniModal.type}`}>
                      <div className="round-modal-icon">
                        {miniModal.type === "correct" ? "✅" : "❌"}
                      </div>
                      <div className={`round-modal-points ${miniModal.type}`}>
                        {miniModal.points > 0
                          ? `+${miniModal.points}`
                          : miniModal.points}
                        {" очков"}
                      </div>
                      <p className="round-modal-message">{miniModal.message}</p>
                      <button
                        className="primary"
                        onClick={() => {
                          setMiniModal(null);
                          setMiniEffect(null);
                          if (miniModal.type === "correct") {
                            nextMiniGameRound();
                          }
                        }}
                      >
                        {miniModal.type === "correct"
                          ? miniGameRound === miniGameRounds.length - 1
                            ? "Завершить"
                            : "Понял, продолжить"
                          : "Понял"}
                      </button>
                    </div>
                  </div>
                )}
              </div>
            )}
          </section>
        )}

        {view === "pre-quiz" && (
          <section className="panel smooth">
            <h2>Стресс-кейс завершён</h2>
            <p>Вы прошли все раунды. Вот ваш результат:</p>

            <div className="stress-result-block">
              <div className="stress-score-display">
                <span className="stress-score-number">{miniScore}</span>
                <span className="stress-score-label">очков из 40</span>
              </div>
              <div
                className={`stress-rating ${
                  miniScore >= 35
                    ? "rating-great"
                    : miniScore >= 20
                      ? "rating-good"
                      : "rating-retry"
                }`}
              >
                {miniScore >= 35
                  ? "⭐⭐⭐ Отличная реакция!"
                  : miniScore >= 20
                    ? "⭐⭐ Хорошо"
                    : "⭐ Попробуйте ещё раз"}
              </div>
              <div className="stress-score-bar-track">
                <div
                  className="stress-score-bar-fill"
                  style={{
                    width: `${Math.max(0, Math.min(100, (miniScore / 40) * 100))}%`,
                  }}
                />
              </div>
            </div>

            <div className="row nav">
              <button
                className="secondary"
                onClick={() => setView("stress-run")}
              >
                Назад
              </button>
              <button className="primary" onClick={() => setView("quiz")}>
                Перейти к тесту
              </button>
            </div>
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
