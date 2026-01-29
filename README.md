# AltGenius - AI-Powered ALT Text Generator for WordPress

![Version](https://img.shields.io/badge/version-1.0.1-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-green.svg)
![License](https://img.shields.io/badge/license-GPL--2.0%2B-orange.svg)

**AltGenius** to zaawansowana wtyczka WordPress do automatycznego generowania tekstów alternatywnych (ALT) dla obrazów za pomocą sztucznej inteligencji OpenAI. Wtyczka znacząco poprawia dostępność strony oraz SEO poprzez inteligentne opisywanie obrazów.

## 🚀 Główne Funkcje

### 🤖 Automatyczne Generowanie ALT

- **Generowanie z AI:** Wykorzystuje modele GPT (gpt-4o-mini, gpt-4.1, o3, o4-mini) do tworzenia dokładnych, kontekstowych opisów obrazów
- **Vision API:** Bezpośrednia analiza obrazu (base64) zamiast URL
- \*\*Kontekst treści:\*\* Automatyczne uwzględnianie kontekstu wpisu/strony/produktu

### ⚡ Automatyzacja (CRON)

- **Częstotliwość:** Co 5 minut (288×/dzień) - działa w tle automatycznie
- **Limit:** 30 obrazków na uruchomienie
- **Wydajność:** ~8,640 zapytań/dzień (bezpieczne dla OpenAI Tier 1: 10,000/dzień)
- **Szczegółowe logowanie** wszystkich operacji do `logs/alt-scan-log.txt`

### 🔄 Gutenberg Sync

- **Dwukierunkowa synchronizacja ALT** między Biblioteką Mediów a blokami obrazów Gutenberg
- **Automatyczna aktualizacja:** Zmiana ALT w bibliotece → aktualizacja we wszystkich postach
- **Odwrotna synchronizacja:** Edycja ALT w Gutenberg → zapis do biblioteki
- **Wsparcie dla bloków:** `wp:image` oraz klasycznych `<img wp-image-*>`

### 📊 Panel Statystyk

- **Dedykowane menu top-level** w WordPress
- **KPI w czasie rzeczywistym:**
  - Wszystkie obrazy w bibliotece
  - Obrazy z ALT
  - Obrazy bez ALT
  - Procent pokrycia
  - **Nieobsługiwane formaty** (SVG itp.) - pokazuje ile obrazów nie może być przetworzonych
- **Status Crona:** Czy aktywny, kiedy następne uruchomienie
- **Informacja o modelu:** Alert pokazujący wspierane formaty obrazów i ograniczenia wybranego modelu AI

### 🛡️ Walidacja Formatów

- **Wspierane formaty OpenAI:** PNG, JPEG, JPG, GIF, WEBP
- **Automatyczna walidacja:** SVG i inne nieobsługiwane formaty są odrzucane przed wysłaniem do API
- **Oszczędność:** Zapobiega błędom 400 i marnowaniu limitów API
- **Przyszłość:** Struktura gotowa na dodanie innych providerów AI (np. Gemini) z innymi obsługiwanymi formatami

### ⚙️ Akcje Masowe

- **Generuj dla zaznaczonych** - przetwarzanie wybranych obrazów w bibliotece mediów
- **Przycisk w bibliotece** - pojedynczy przycisk "Generuj ALT" dla każdego obrazu

### 🔄 Automatyczne Aktualizacje

- **Integracja z GitHub Releases** - automatyczne pobieranie aktualizacji z `kacperbaranowski/AltGenius`
- **Publiczne repo** - brak potrzeby tokena
- **Bezpieczne aktualizacje** - zachowanie ustawień

## 📦 Instalacja

### Metoda 1: Przez panel WordPress

1. Pobierz najnowszą wersję z [GitHub Releases](https://github.com/kacperbaranowski/AltGenius/releases)
2. Przejdź do **Wtyczki → Dodaj nową → Wyślij wtyczkę na serwer**
3. Wybierz pobrany plik ZIP
4. Kliknij **Instaluj teraz**
5. **Aktywuj** wtyczkę

### Metoda 2: Manualna instalacja

1. Pobierz wtyczkę z [GitHub](https://github.com/kacperbaranowski/AltGenius)
2. Wypakuj folder do `/wp-content/plugins/`
3. Aktywuj wtyczkę w panelu WordPress

### Metoda 3: Git (dla deweloperów)

```bash
cd wp-content/plugins/
git clone https://github.com/kacperbaranowski/AltGenius.git wp-alt-generator
```

## ⚙️ Konfiguracja

### 1. Uzyskaj API Key OpenAI

1. Przejdź do [platform.openai.com](https://platform.openai.com/api-keys)
2. Zaloguj się lub utwórz konto
3. Utwórz nowy API key
4. Skopiuj klucz (zachowaj w bezpiecznym miejscu!)

### 2. Konfiguracja wtyczki

1. W WordPress przejdź do **AltGenius → Ustawienia**
2. Wklej **API Key** w odpowiednie pole
3. Wybierz **Model** (zalecany: `gpt-4o-mini` dla najlepszego stosunku ceny do jakości)
4. (Opcjonalnie) Dostosuj **Prompt** do swoich potrzeb
5. (Opcjonalnie) Włącz **Automatyczne generowanie przy uploadzie**
6. Kliknij **Zapisz zmiany**

## 📖 Użycie

### Panel Statystyk

1. Przejdź do **AltGenius → Statystyki**
2. Zobacz:
   - **Card-y KPI:** Wszystkie obrazy, Z ALT, Bez ALT, Pokrycie %
   - **Status Crona:** Czy aktywny, kiedy następne uruchomienie (co 5 minut)
   - Info: Cron przetwarza ~8,640 obrazków/dzień

### Generowanie ALT dla pojedynczego obrazu

1. Przejdź do **Media → Biblioteka**
2. Znajdź obraz bez ALT
3. Kliknij przycisk **Generuj ALT**
4. Poczekaj na wygenerowanie (status pojawi się obok przycisku)

### Masowe generowanie ALT

1. Przejdź do **Media → Biblioteka**
2. Zaznacz obrazy (checkbox obok miniatur)
3. Z menu **Akcje masowe** wybierz **Generuj ALT dla zaznaczonych**
4. Kliknij **Zastosuj**

### Automatyczne Generowanie (CRON)

Cron działa automatycznie co 5 minut i:

- Skanuje bibliotekę mediów pod kątem obrazków bez ALT
- Przetwarza 30 obrazków na jednym uruchomieniu
- Loguje wszystkie operacje do `logs/alt-scan-log.txt`
- **Nie wymaga żadnej interwencji** - działa w tle 24/7

**Status Crona:** Sprawdź w **AltGenius → Statystyki**

## 🎨 Dostosowywanie

### Zmiana Promptu

Domyślny prompt:

```
Opisz to zdjęcie jednym zdaniem po polsku do ALT. URL: {{image_url}}
```

Możesz go dostosować w **AltGenius → Ustawienia → Prompt**. Użyj `{{image_url}}` jako placeholdera.

Przykłady:

```
Stwórz krótki, opisowy alt text dla tego obrazu: {{image_url}}
```

```
Wygeneruj alt text zgodny z WCAG 2.1 dla: {{image_url}}
```

### Zmiana częstotliwości Crona (zaawansowane)

Domyślnie: co 5 minut. Aby zmienić, edytuj w pliku wtyczki (linia ~537):

```php
$schedules['every_5_minutes'] = [
    'interval' => 300, // 300 sekund = 5 minut
    'display' => __('Co 5 minut')
];
```

Przykładowe wartości:

- `60` - co minutę (nie zalecane - rate limits!)
- `300` - co 5 minut (domyślne, zalecane dla Tier 1)
- `600` - co 10 minut
- `1800` - co 30 minut

**⚠️ Uwaga:** Po zmianie musisz dezaktywować i aktywować wtyczkę!

### Zmiana limitu przetwarzania

Domyślnie: 30 obrazków/batch (optymalne dla OpenAI Tier 1).

Edytuj w ustawieniach domyślnych (linia ~78):

```php
'scan_limit' => 30
```

**⚠️ Uwaga:**

- Tier 1 (10,000 RPD): max ~35 przy 5-minutowym interwale
- Tier 2+ (50,000 RPD): możesz zwiększyć do 100-150

## 🗂️ Struktura Plików

```
wp-alt-generator/
├── wp-alt-generator.php    # Główny plik wtyczki
├── assets/
│   ├── altgpt.js           # JS dla biblioteki mediów
│   ├── stats.js            # JS dla panelu statystyk
│   └── stats.css           # Style dla panelu statystyk
├── logs/
│   └── alt-scan-log.txt    # Plik logów (tworzony automatycznie)
└── README.md
```

## 🔐 Bezpieczeństwo

- **API Key:** Przechowywany bezpiecznie w bazie danych WordPress
- **Nonce verification:** Wszystkie akcje AJAX zabezpieczone
- **Capability checks:** Tylko administratorzy mają dostęp (`manage_options`)

## 💰 Koszty API OpenAI

Wtyczka używa **Vision API** (analiza obrazu), co ma wpływ na koszty:

### OpenAI Tier 1 (10,000 RPD)

- **Koszt za obraz:** ~$0.001 - $0.003 (model: gpt-4o-mini)
- **Dzienna wydajność:** ~8,640 obrazków (z ustawieniami domyślnymi)
- **Dzienny koszt:** ~$8.64 - $25.92
- **Miesięczny koszt:** ~$259 - $777

### Przykłady (gpt-4o-mini):

- **100 obrazów:** ~$0.10 - $0.30
- **1,000 obrazów:** ~$1.00 - $3.00
- **10,000 obrazów:** ~$10.00 - $30.00

💡 **Wskazówka:** Użyj `gpt-4o-mini` dla najniższych kosztów z zachowaniem dobrej jakości!

Sprawdź aktualne ceny na [OpenAI Pricing](https://openai.com/api/pricing/).

## 🐛 Rozwiązywanie Problemów

### "Brak API key"

- Upewnij się, że API key jest poprawnie wklejony w **AltGenius → Ustawienia**
- Sprawdź czy nie ma dodatkowych spacji

### "OpenAI error 401"

- API key jest nieprawidłowy lub wygasł
- Wygeneruj nowy klucz na platform.openai.com

### "OpenAI error 429" (Rate Limit)

- Przekroczono limit zapytań API
- **Tier 1:** Zmniejsz limit do 20-25 obrazków lub zwiększ interwał do 10 minut
- **Rozwiązanie:** Upgrade do Tier 2+ na OpenAI

### Cron nie działa

- **Sprawdź status:** **AltGenius → Statystyki** → sekcja "Automatyczne Generowanie"
- **Zresetuj cron:** Dezaktywuj i aktywuj wtyczkę ponownie
- **WordPress Cron:** Sprawdź czy nie jest wyłączony (`DISABLE_WP_CRON`)
- **Logi:** Sprawdź `logs/alt-scan-log.txt` pod kątem błędów

### Gutenberg Sync nie działa

- **Weryfikacja:** Edytuj post w Gutenberg i zmień ALT obrazu
- **Sprawdź logi:** Zapisane w `logs/alt-scan-log.txt`
- **Cache:** Wyczyść cache WordPress i przeglądarki

### Wysokie użycie API

- Zmniejsz limit z 30 do 20 (linia ~78)
- Zwiększ interwał z 5 do 10 minut (linia ~537)
- Monitoruj usage na [platform.openai.com/usage](https://platform.openai.com/usage)

## 🤝 Wsparcie i Zgłaszanie Błędów

- **Issues:** [GitHub Issues](https://github.com/kacperbaranowski/AltGenius/issues)
- **Autor:** Kacper Baranowski
- **GitHub:** [@kacperbaranowski](https://github.com/kacperbaranowski)

## 📄 Licencja

GPLv2 or later - [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

---

**Czy ta wtyczka była pomocna? Zostaw ⭐ na [GitHub](https://github.com/kacperbaranowski/AltGenius)!**
