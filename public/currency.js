(() => {
  const select = document.querySelector("#currencySelect");
  const status = document.querySelector("#currencyStatus");
  if (!select || !status) return;

  let currency = "NGN";
  let rates = { NGN: 1 };

  function renderPrices() {
    document.querySelectorAll("[data-price-ngn]").forEach((element) => {
      const amount = Number(element.dataset.priceNgn) * rates[currency];
      try {
        element.textContent = new Intl.NumberFormat(undefined, {
          style: "currency",
          currency,
        }).format(amount);
      } catch {
        element.textContent = `${currency} ${amount.toFixed(2)}`;
      }
    });
  }

  function updateCurrency(next) {
    if (!Object.hasOwn(rates, next)) {
      select.value = currency;
      return;
    }
    currency = next;
    try {
      localStorage.setItem("ec-currency", currency);
    } catch {
      status.textContent = "Currency preference could not be saved; it will reset when you leave.";
    }
    renderPrices();
  }

  async function load() {
    try {
      const response = await fetch("/api/currency");
      if (!response.ok) throw new Error(`Rate service returned ${response.status}`);
      const data = await response.json();
      if (data.base !== "NGN" || !data.rates || !Object.hasOwn(data.rates, "NGN")) {
        throw new Error("Rate service returned invalid data");
      }
      rates = data.rates;

      let saved = "";
      try {
        saved = localStorage.getItem("ec-currency") || "";
      } catch {
        status.textContent = "Browser storage unavailable; choose a display currency.";
      }

      let countryName = "";
      let detectedCurrency = "";
      let locationUnavailable = false;
      try {
        const locationResponse = await fetch("https://ipapi.co/json/");
        if (!locationResponse.ok) throw new Error(`Location service returned ${locationResponse.status}`);
        const location = await locationResponse.json();
        countryName = location.country_name || "";
        detectedCurrency = location.currency || "";
      } catch {
        locationUnavailable = true;
      }

      const codes = Object.keys(rates).filter((code) => /^[A-Z]{3}$/.test(code)).sort();
      select.replaceChildren(...codes.map((code) => {
        const option = document.createElement("option");
        option.value = code;
        option.textContent = code;
        return option;
      }));

      currency = saved && Object.hasOwn(rates, saved)
        ? saved
        : Object.hasOwn(rates, detectedCurrency)
          ? detectedCurrency
          : "NGN";
      select.value = currency;
      renderPrices();
      const locationStatus = countryName
        ? `Approximate region by IP: ${countryName}. `
        : locationUnavailable
          ? "Location lookup unavailable; choose a currency. "
          : "Region not returned; choose a currency. ";
      status.textContent = `${locationStatus}Prices are estimates converted from NGN; Paystack checkout is charged in NGN.`;
    } catch {
      rates = { NGN: 1 };
      currency = "NGN";
      select.replaceChildren(new Option("NGN", "NGN"));
      select.value = "NGN";
      renderPrices();
      status.textContent = "Exchange rates unavailable; prices are shown in NGN.";
    }
  }

  select.addEventListener("change", () => updateCurrency(select.value));
  load();
})();
