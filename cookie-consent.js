
(() => {
  // ВСТАВЬ СЮДА НОМЕР СВОЕГО СЧЁТЧИКА
  const METRIKA_ID = 114097352;

  const CONSENT_KEY = "vne_cookie_consent";
  const CONSENT_VERSION = "1";

  const banner = document.getElementById("vne-cookie-banner");
  const settingsButton = document.getElementById("vne-cookie-settings");

  if (!banner || !settingsButton) return;

  let metrikaLoaded = false;

  // Загружаем Метрику только после согласия
  function loadMetrika() {
    if (metrikaLoaded) return;
    metrikaLoaded = true;

    (function(m, e, t, r, i, k, a) {
      m[i] = m[i] || function() {
        (m[i].a = m[i].a || []).push(arguments);
      };
      m[i].l = 1 * new Date();

      k = e.createElement(t);
      a = e.getElementsByTagName(t)[0];
      k.async = 1;
      k.src = r;
      a.parentNode.insertBefore(k, a);
    })(
      window,
      document,
      "script",
      "https://mc.yandex.ru/metrika/tag.js",
      "ym"
    );

    ym(METRIKA_ID, "init", {
      clickmap: true,
      trackLinks: true,
      accurateTrackBounce: true,
      webvisor: false
    });
  }

  function saveConsent(value) {
    localStorage.setItem(
      CONSENT_KEY,
      JSON.stringify({
        version: CONSENT_VERSION,
        analytics: value,
        date: new Date().toISOString()
      })
    );

    banner.classList.remove("visible");
    settingsButton.classList.add("visible");

    if (value === true) {
      loadMetrika();
    }
  }

  function showBanner() {
    banner.classList.add("visible");
    settingsButton.classList.remove("visible");
  }

  function getConsent() {
    try {
      const saved = JSON.parse(
        localStorage.getItem(CONSENT_KEY)
      );

      if (
        saved &&
        saved.version === CONSENT_VERSION &&
        typeof saved.analytics === "boolean"
      ) {
        return saved;
      }
    } catch (error) {
      console.warn("Cookie consent error:", error);
    }

    return null;
  }

  // Кнопки баннера
  document
    .getElementById("vne-cookie-accept")
    .addEventListener("click", () => saveConsent(true));

  document
    .getElementById("vne-cookie-reject")
    .addEventListener("click", () => saveConsent(false));

  // Возможность изменить решение
  settingsButton.addEventListener("click", showBanner);

  // Проверяем сохранённое согласие
  const consent = getConsent();

  if (consent) {
    settingsButton.classList.add("visible");

    if (consent.analytics) {
      loadMetrika();
    }
  } else {
    showBanner();
  }
})();
