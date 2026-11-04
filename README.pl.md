# Laravel dane.gov.pl

[![tests](https://github.com/beziworld/laravel-dane-gov/actions/workflows/tests.yml/badge.svg)](https://github.com/beziworld/laravel-dane-gov/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/beziworld/laravel-dane-gov.svg)](https://packagist.org/packages/beziworld/laravel-dane-gov)
[![License](https://img.shields.io/packagist/l/beziworld/laravel-dane-gov.svg)](LICENSE.md)

Klient Laravel dla [API portalu dane.gov.pl](https://api.dane.gov.pl/doc), polskiego portalu otwartych danych:
dziesiątki tysięcy zbiorów publikowanych przez ministerstwa, urzędy, województwa i gminy.

- zbiory danych z wyszukiwaniem pełnotekstowym, filtrami, sortowaniem i fasetami (`by_institution`, `by_format`, ...)
- zasoby (pliki, linki, API) z plikami po konwersji (CSV, JSON-LD) i metadanymi
- instytucje (dostawcy), przykłady wykorzystania (aplikacje i serwisy zbudowane na danych) i wyszukiwarka
- typowane DTO: `Dataset`, `Resource`, `Institution`, `Showcase`, `SearchResult`, `TabularRow`, `Page`
- leniwe iteratory (`LazyCollection`): kolejne strony są pobierane dopiero podczas iteracji
- czytnik danych tabelarycznych, który strumieniuje wiersze zasobu z filtrami, zakresami, sortowaniem, sumami i średnimi
- pobieranie plików zasobów strumieniowo na dysk
- cache odpowiedzi, wybór wersji API i języka, komendy Artisan `dane:search` i `dane:resource`

[English documentation](README.md)

## Dlaczego ten pakiet

W chwili pisania Packagist nie ma pakietu dla API dane.gov.pl (wyszukiwania „dane.gov.pl”, „danegov” i „otwarte
dane” nie zwracają wyników), a na GitHubie są klienty tylko w Pythonie, Go i R. Ten pakiet daje typowanego klienta
Laravel z leniwym stronicowaniem, builderem filtrów i czytnikiem danych tabelarycznych.

## Instalacja

```bash
composer require beziworld/laravel-dane-gov
php artisan vendor:publish --tag=dane-gov-config   # opcjonalnie
```

Klucz ani rejestracja nie są potrzebne. Opcjonalne ustawienia `.env`:

```dotenv
DANE_GOV_API_VERSION=1.4      # wysyłana jako X-API-VERSION i prefiks ścieżki
DANE_GOV_LANG=pl              # pl lub en (Accept-Language)
DANE_GOV_PER_PAGE=100         # 1-100, rekordów na stronę w leniwych iteratorach
DANE_GOV_TIMEOUT=30
DANE_GOV_RETRIES=2            # błędy połączenia i HTTP 5xx przy odczytach
DANE_GOV_CACHE=true
DANE_GOV_CACHE_STORE=
DANE_GOV_CACHE_TTL=3600       # sekundy; 0 wyłącza cache
DANE_GOV_DOWNLOAD_TIMEOUT=600
DANE_GOV_DOWNLOAD_DIRECTORY=  # domyślnie storage/app/dane-gov
```

## Zbiory danych

```php
use Beziworld\DaneGov\Facades\DaneGov;
use Beziworld\DaneGov\Query;

foreach (DaneGov::datasets('jakość powietrza') as $dataset) {   // wszystkie strony, leniwie
    $dataset->title;            // bez podświetlenia wyników
    $dataset->institutionId;
    $dataset->formats;          // ["csv", "xlsx"]
    $dataset->modified;         // CarbonImmutable, Europe/Warsaw
}

$query = Query::make()
    ->search('ludność')
    ->where('institution.id', 34)              // institution[id][term]=34
    ->where('format', ['csv', 'xlsx'])         // format[terms]=csv,xlsx
    ->where('openness_score', 3, 'gte')        // openness_score[gte]=3
    ->where('has_high_value_data', true)
    ->sort('-modified')
    ->facets('by_institution', 'by_format');

$page = DaneGov::datasetsPage($query);         // jedna strona z liczbą wyników i fasetami
$page->total;                                  // liczba wyników
$page->facet('by_institution')[0]->label();    // nazwa instytucji
$page->facet('by_institution')[0]->count;

$dataset = DaneGov::dataset(568);
$dataset->resourcesCount;                      // 26
$dataset->portalUrl();                         // https://dane.gov.pl/pl/dataset/568,...
DaneGov::datasetResources(568)->all();
DaneGov::datasetShowcases(568)->all();
```

## Zasoby, instytucje, przykłady wykorzystania i wyszukiwarka

```php
$resource = DaneGov::resource(8162);
$resource->format;              // "csv"
$resource->hasTabularData;      // true: wiersze dostępne przez table()
$resource->availableFormats();  // ["csv"] albo ["xlsx", "csv", "jsonld"]
$resource->datasetId;           // 568

DaneGov::resources(Query::make()->search('budżet')->where('format', 'csv'))->take(10)->all();

$gus = DaneGov::institution(34);
$gus->abbreviation;             // "GUS"
$gus->address();                // "al. Niepodległości 208, 00-925 Warszawa"
DaneGov::institutionDatasets(34)->take(20)->all();
DaneGov::institutions('statystyczny')->all();

DaneGov::showcases()->take(5)->pluck('title');

$hits = DaneGov::search(Query::make()->search('powietrze')->where('model', ['dataset', 'resource']));
DaneGov::searchPage('powietrze')->aggregations['counters'];   // ["datasets" => ..., "resources" => ...]

DaneGov::inLanguage('en')->dataset(568);
DaneGov::get('dga-aggregated');   // surowy dokument JSON:API dowolnego innego endpointu
```

## Dane tabelaryczne

Pliki zaimportowane przez portal (CSV, XLSX i podobne) są dostępne wiersz po wierszu. Kolumny mają nazwy techniczne
(`col1`, `col2`, ...) i etykiety z nagłówka.

```php
$table = DaneGov::table(8162)                 // „Nazwiska męskie wg województw”
    ->where('col3', 'NOWAK')                  // dokładna wartość, escapowana i cytowana w razie potrzeby
    ->whereBetween('col4', 1000, null)        // col4:[1000 TO *]
    ->whereIn('col1', ['MAZOWIECKIE', 'ŚLĄSKIE'])
    ->query('col3:NOWAK*')                    // surowa składnia zapytań: wildcardy, OR, fuzzy, regex
    ->sort('-col4');

$table->count();                              // liczba pasujących wierszy, jedno małe zapytanie
$table->columns();                            // list<Column>: name "col3", label "Nazwisko", type "string"

foreach ($table->rows() as $row) {            // strumieniowo, strona po stronie
    $row->get('Nazwisko');                    // po etykiecie albo nazwie kolumny
    $row->text('col4');                       // reprezentacja tekstowa
    $row->toNamedArray();                     // ["Województwo" => ..., "Nazwisko" => ..., ...]
}

DaneGov::table(8162)->where('col3', 'NOWAK')->sum('col4')->avg('col4')->aggregations();
// ["sum" => ["col4" => 93018.0], "avg" => ["col4" => ...]]

DaneGov::table(8162)->limit(500)->rows();     // zatrzymaj po 500 wierszach
DaneGov::row(8162, 'f9bdaec6-c616-5193-a517-d448b9043668');
```

API stronicuje tylko pierwsze 10 000 rekordów każdego wyniku (głębsze strony kończą się HTTP 400). Czytnik rzuca
`ResultWindowExceeded`, zanim poprosi o taką stronę; zawęź zapytanie filtrami, użyj `limit()` albo pobierz plik,
aby przetworzyć wszystkie wiersze.

## Pobieranie plików

```php
$file = DaneGov::download(8162);                          // do storage/app/dane-gov/<nazwa pliku>
$file = DaneGov::download(54119, storage_path('imports/'), 'csv');  // CSV po konwersji zasobu XLSX
$file = DaneGov::download($resource, '/tmp/nazwiska.csv');

$file->path;    $file->bytes;    $file->contentType;    $file->url;
```

Pliki są zapisywane strumieniowo (bez buforowania w pamięci), najpierw do pliku `.part`, a po zakończeniu
przemianowywane. Przekierowania do serwera plików są obsługiwane; akceptowane są tylko adresy http i https.

## Artisan

```bash
php artisan dane:search "jakość powietrza" --facets
php artisan dane:search budżet --resources --format=csv --institution=34 --sort=-modified --limit=50
php artisan dane:resource 8162 --rows=20 --where=col1=MAZOWIECKIE --sort=-col4
php artisan dane:resource 8162 --q="col3:KOWAL*" --rows=5
php artisan dane:resource 54119 --rows=0 --download=storage/app/imports/ --format=csv
```

## Błędy

| Sytuacja | Wynik |
|----------|-------|
| HTTP 404 (nieznany zbiór, zasób, instytucja, wiersz) | `NotFound` |
| HTTP 422 (błędny parametr, np. per_page powyżej 100) | `ApiException` (`isValidationError()`, `errors`, wskaźnik parametru w komunikacie) |
| HTTP 400, HTTP 5xx po ponowieniach | `ApiException` (`httpStatus`, `errorCode`, `isServerError()`) |
| Błąd połączenia lub timeout po ponowieniach | `ApiException` opakowujący `ConnectionException` |
| Iteracja poza pierwsze 10 000 rekordów | `ResultWindowExceeded` (`total`, `window`) |
| Brak pliku, nieznany format, błąd HTTP lub dysku przy pobieraniu | `DownloadFailed` |
| Błędna konfiguracja | `InvalidConfiguration` |
| Błędna nazwa pola, lookup, kolumna, rozmiar strony | `InvalidArgumentException` |

Wszystkie wyjątki pakietu dziedziczą po `Beziworld\DaneGov\Exceptions\DaneGovException`.

## Rozwój

```bash
composer install
composer ci   # pint --test, phpstan (poziom 8), pest
```

Testy używają odpowiedzi zarejestrowanych z działającego API (`tests/Fixtures`); żadne zapytanie nie wychodzi
poza testy.

## Autor

Pakiet tworzy i utrzymuje **Kamil Ćwiertnia**, [beziworld.eu](https://beziworld.eu).

- E-mail: [biuro@beziworld.eu](mailto:biuro@beziworld.eu)
- Zgłoszenia: [GitHub issues](https://github.com/beziworld/laravel-dane-gov/issues)

Potrzebujesz integracji? Skontaktuj się przez [beziworld.eu](https://beziworld.eu).

### Inne pakiety autora

[laravel-gus-bdl](https://github.com/beziworld/laravel-gus-bdl) ·
[laravel-teryt](https://github.com/beziworld/laravel-teryt) ·
[laravel-krs](https://github.com/beziworld/laravel-krs) ·
[laravel-nbp](https://github.com/beziworld/laravel-nbp)

## Licencja

MIT. Zob. [LICENSE.md](LICENSE.md). Dane z dane.gov.pl udostępniają ich dostawcy na warunkach podanych przy każdym
zbiorze (licencja i warunki ponownego wykorzystania). Pakiet nie jest powiązany z Ministerstwem Cyfryzacji ani
operatorami portalu dane.gov.pl ani przez nich wspierany.
