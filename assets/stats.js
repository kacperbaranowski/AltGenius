jQuery(document).ready(function ($) {
  const ajax = ALTGPT_STATS.ajax;
  const nonce = ALTGPT_STATS.nonce;

  function showStatus(message, type = "info") {
    const $status = $(".altgpt-action-status");
    $status.text(message);
    $status.css(
      "color",
      type === "error" ? "#d63638" : type === "success" ? "#00a32a" : "#000",
    );

    if (type !== "loading") {
      setTimeout(() => $status.text(""), 3000);
    }
  }

  function refreshStats() {
    $.post(
      ajax,
      {
        action: "altgpt_refresh_stats",
        nonce: nonce,
      },
      function (response) {
        if (response.success) {
          location.reload();
        }
      },
    );
  }

  // Skanuj teraz
  $(".altgpt-scan-now").on("click", function () {
    const $btn = $(this);
    $btn.prop("disabled", true);
    showStatus("Skanowanie...", "loading");

    $.post(
      ajax,
      {
        action: "altgpt_scan_now",
        nonce: nonce,
      },
      function (response) {
        if (response.success) {
          showStatus(response.data.message, "success");
          setTimeout(() => location.reload(), 1000);
        } else {
          showStatus("Błąd skanowania", "error");
        }
        $btn.prop("disabled", false);
      },
    );
  });

  // Generuj dla wszystkich
  $(".altgpt-generate-all").on("click", function () {
    const $btn = $(this);
    if (
      !confirm(
        "Czy na pewno chcesz wygenerować ALT dla wszystkich obrazków bez ALT? To może zająć trochę czasu.",
      )
    ) {
      return;
    }

    $btn.prop("disabled", true);
    let totalProcessed = 0;

    function processNext() {
      showStatus(
        `Przetwarzanie... (${totalProcessed} przetworzonych)`,
        "loading",
      );

      $.post(
        ajax,
        {
          action: "altgpt_generate_all",
          nonce: nonce,
        },
        function (response) {
          if (response.success) {
            const data = response.data;
            totalProcessed += data.processed;

            showStatus(
              `Przetworzono ${data.processed} (OK: ${data.ok}, Błędy: ${data.err}). Pozostało: ${data.remaining}`,
              "info",
            );

            if (data.remaining > 0) {
              setTimeout(processNext, 2000); // Poczekaj 2s przed kolejną iteracją
            } else {
              showStatus(
                "✅ Zakończono! Wszystkie obrazki mają ALT.",
                "success",
              );
              setTimeout(() => location.reload(), 2000);
            }
          } else {
            showStatus("Błąd podczas generowania", "error");
            $btn.prop("disabled", false);
          }
        },
      ).fail(function () {
        showStatus("Błąd połączenia", "error");
        $btn.prop("disabled", false);
      });
    }

    processNext();
  });

  // Odśwież logi
  $(".altgpt-refresh-logs").on("click", function () {
    const $btn = $(this);
    $btn.prop("disabled", true).text("🔄 Ładowanie...");

    $.post(
      ajax,
      {
        action: "altgpt_refresh_logs",
        nonce: nonce,
      },
      function (response) {
        if (response.success) {
          const logs = response.data.logs || "Brak logów";
          $(".altgpt-logs").html(
            logs ? logs.replace(/\\n/g, "<br>") : "<em>Brak logów</em>",
          );
        }
        $btn.prop("disabled", false).text("🔄 Odśwież");
      },
    );
  });

  // Wyczyść logi
  $(".altgpt-clear-logs").on("click", function () {
    if (!confirm("Czy na pewno chcesz wyczyścić wszystkie logi?")) {
      return;
    }

    const $btn = $(this);
    $btn.prop("disabled", true);

    $.post(
      ajax,
      {
        action: "altgpt_clear_logs",
        nonce: nonce,
      },
      function (response) {
        if (response.success) {
          $(".altgpt-logs").html("<em>Brak logów</em>");
          alert("Logi wyczyszczone");
        }
        $btn.prop("disabled", false);
      },
    );
  });
});
