<?php

use App\Models\Author;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // -----------------------------------------------------------------------
        // 1. Kategorie
        // -----------------------------------------------------------------------
        $devLog = Category::firstOrCreate(
            ['slug' => 'dev-log'],
            ['name' => 'Dev Log', 'color' => 'sky', 'icon' => '📓']
        );

        $architecture = Category::firstOrCreate(
            ['slug' => 'architektura'],
            ['name' => 'Architektura', 'color' => 'slate', 'icon' => '🏗️']
        );

        // -----------------------------------------------------------------------
        // 2. Autor (portfolio owner, user_id = 1)
        // -----------------------------------------------------------------------
        $author = Author::firstOrCreate(
            ['user_id' => 1],
            [
                'name'            => 'Szymon Borowski',
                'email'           => 'szymon@borowski.dev',
                'user_created_at' => now(),
            ]
        );

        // -----------------------------------------------------------------------
        // 3. Post 1 – Dev Log: wyszukiwarka (overview)
        // -----------------------------------------------------------------------
        if (!Post::where('slug', 'wyszukiwarka-blog-meilisearch')->exists()) {
            $post1 = Post::create([
                'uuid'         => Str::uuid(),
                'author_id'    => $author->user_id,
                'slug'         => 'wyszukiwarka-blog-meilisearch',
                'status'       => 'published',
                'published_at' => now(),
            ]);

            $post1->translations()->create([
                'locale'  => 'pl',
                'version' => 1,
                'title'   => 'Wyszukiwarka na blogu – Meilisearch w akcji',
                'excerpt' => 'Na blogu pojawiła się wyszukiwarka. Krótkie podsumowanie tego, co zostało zbudowane: search bar w nawigacji, strona wyników oraz silnik oparty o Meilisearch.',
                'content' => <<<'HTML'
<p>Do bloga trafiła właśnie nowa funkcja, na którą czekałem od dawna — wyszukiwarka. Do tej pory nawigacja po treściach odbywała się wyłącznie przez kategorie i tagi; teraz można szybko dotrzeć do konkretnych artykułów, wpisując kilka słów kluczowych.</p>

<h2>Search bar w nawigacji</h2>
<p>W górnym pasku nawigacyjnym pojawiła się ikona lupy. Jej kliknięcie otwiera modal z polem wyszukiwania. Wyniki pojawiają się na bieżąco — z debouncingiem 300&nbsp;ms — i są pogrupowane w trzy sekcje: <strong>posty</strong>, <strong>kategorie</strong> i <strong>tagi</strong>. Każdy wynik jest klikalnym linkiem prowadzącym bezpośrednio do danej strony. Pasujące fragmenty tekstu są podświetlane znacznikiem <code>&lt;mark&gt;</code>. Naciśnięcie klawisza <kbd>ESC</kbd> zamyka modal.</p>

<p>Komponent działa jako Livewire + Alpine.js: Livewire odpytuje backend przy każdej zmianie pola, Alpine kontroluje focus i animację.</p>

<h2>Strona wyszukiwania</h2>
<p>Po wpisaniu frazy i naciśnięciu <kbd>Enter</kbd> użytkownik trafia na stronę wyników (<code>/posts?q=…</code>), która wyświetla pełną listę pasujących artykułów z paginacją. Link „Pokaż wszystkie wyniki" w modalu również przenosi na tę stronę.</p>

<h2>Silnik – Meilisearch</h2>
<p>Pod spodem działa <strong>Meilisearch v1.11</strong> — szybki, tolerancyjny na literówki silnik full-text search napisany w Rust. Indeks postów obejmuje tytuł, excerpt, treść artykułu, przypisane kategorie i tagi. Jednocześnie przeszukiwane są trzy indeksy: <code>posts</code>, <code>categories</code> i <code>tags</code>.</p>

<p>Meilisearch jest zintegrowany z Laravel Scout, co pozwala na automatyczne utrzymywanie indeksu w synchronizacji z bazą danych — każda zmiana statusu posta wyzwala asynchroniczną aktualizację indeksu przez kolejkę Laravel.</p>
HTML,
            ]);

            $post1->categories()->attach($devLog->id);
        }

        // -----------------------------------------------------------------------
        // 4. Post 2 – Architektura: szczegóły wdrożenia Meilisearch
        // -----------------------------------------------------------------------
        if (!Post::where('slug', 'wdrozenie-meilisearch-docker-compose-kubernetes')->exists()) {
            $post2 = Post::create([
                'uuid'         => Str::uuid(),
                'author_id'    => $author->user_id,
                'slug'         => 'wdrozenie-meilisearch-docker-compose-kubernetes',
                'status'       => 'published',
                'published_at' => now(),
            ]);

            $post2->translations()->create([
                'locale'  => 'pl',
                'version' => 1,
                'title'   => 'Wdrożenie Meilisearch – od Docker Compose do Kubernetes',
                'excerpt' => 'Szczegółowy opis integracji Meilisearch z projektem portfolio: konfiguracja serwisu w Docker Compose, manifest StatefulSet w Kubernetes oraz zmiany w serwisie blog umożliwiające indeksację i asynchroniczne aktualizacje indeksów przez kolejkę Laravel.',
                'content' => <<<'HTML'
<p>Meilisearch to szybki silnik full-text search napisany w Rust, wyróżniający się bardzo niskim czasem odpowiedzi i wbudowaną tolerancją na literówki. Poniżej opisuję krok po kroku, jak został wdrożony w projekcie portfolio.</p>

<h2>Docker Compose – środowisko developerskie</h2>
<p>Do <code>blog/docker-compose.yml</code> dodany został serwis <code>blog-meilisearch</code>:</p>

<pre><code>blog-meilisearch:
  image: getmeili/meilisearch:v1.11
  environment:
    MEILI_MASTER_KEY: ${MEILISEARCH_MASTER_KEY}
    MEILI_ENV: development
  volumes:
    - blog_meilisearch_data:/meili_data
  ports:
    - "127.0.0.1:7700:7700"
  healthcheck:
    test: ["CMD-SHELL", "wget --spider -q http://localhost:7700/health"]
    interval: 10s
    timeout: 5s
    retries: 5
    start_period: 30s
  networks:
    - blog-internal</code></pre>

<p>Serwis jest podłączony wyłącznie do sieci <code>blog-internal</code> — nie jest eksponowany w sieci <code>microservices</code> ani <code>web</code>. Port <code>7700</code> jest bindowany tylko na <code>127.0.0.1</code>, więc jest dostępny lokalnie wyłącznie dla potrzeb deweloperskich (np. przez panel Meilisearch).</p>

<p>Serwisy <code>blog-app</code> i <code>blog-consumer</code> otrzymały zmienne środowiskowe wskazujące na Meilisearch oraz warunek <code>service_healthy</code> w <code>depends_on</code>, gwarantujący, że aplikacja nie uruchomi się przed gotowością silnika:</p>

<pre><code>MEILISEARCH_HOST: http://blog-meilisearch:7700
MEILISEARCH_KEY: ${MEILISEARCH_MASTER_KEY}
SCOUT_DRIVER: meilisearch</code></pre>

<h2>Docker Compose – produkcja (docker-compose.prod.yml)</h2>
<p>W produkcyjnym pliku <code>docker-compose.prod.yml</code> serwis wygląda analogicznie, z tą różnicą, że <code>MEILI_ENV</code> jest ustawiony na <code>production</code> (co wyłącza panel administracyjny Meilisearch i wymusza użycie klucza master). Port nie jest eksponowany na hoście — komunikacja odbywa się wyłącznie wewnątrz sieci <code>blog-internal</code>. Dane indeksu są przechowywane w nazwanym wolumenie <code>blog_meilisearch_data</code>.</p>

<h2>Kubernetes</h2>
<p>W warstwie produkcyjnej (ArgoCD + K8s) Meilisearch działa jako <code>StatefulSet</code> w namespace <code>portfolio</code>. Kluczowe elementy konfiguracji:</p>

<ul>
  <li><strong>StatefulSet</strong> z jedną repliką — gwarantuje stabilną tożsamość podu i stałe podpięcie wolumenu.</li>
  <li><strong>Headless ClusterIP Service</strong> (<code>clusterIP: None</code>) na porcie 7700 — umożliwia adresowanie podu po DNS (<code>blog-meilisearch.portfolio.svc.cluster.local</code>) bez load balancera.</li>
  <li><strong>PersistentVolumeClaim</strong> o pojemności 1&nbsp;Gi (<code>accessModes: ReadWriteOnce</code>) generowany przez <code>volumeClaimTemplates</code> — dane indeksu przeżywają restart podu.</li>
  <li>Klucz master key pochodzi z Kubernetes Secret (<code>blog-secret</code>, klucz <code>MEILISEARCH_MASTER_KEY</code>), nie jest hardkodowany w manifeście.</li>
  <li>Liveness probe i readiness probe na endpoincie <code>/health</code> — Kubernetes odtwarza pod przy awarii i nie kieruje ruchu do niegotowego kontenera.</li>
</ul>

<pre><code>env:
  - name: MEILI_ENV
    value: production
  - name: MEILI_MASTER_KEY
    valueFrom:
      secretKeyRef:
        name: blog-secret
        key: MEILISEARCH_MASTER_KEY
resources:
  requests:
    cpu: 50m
    memory: 128Mi
  limits:
    memory: 256Mi</code></pre>

<h2>Zmiany w serwisie blog</h2>

<h3>Zależności</h3>
<p>Do <code>composer.json</code> trafiły dwie paczki:</p>
<ul>
  <li><code>laravel/scout</code> — oficjalny pakiet wyszukiwania Laravel; dostarcza trait <code>Searchable</code> i interfejs do różnych silników.</li>
  <li><code>meilisearch/meilisearch-php</code> — oficjalne PHP SDK Meilisearch, używane przez Scout pod spodem.</li>
</ul>

<h3>Trait Searchable i metody modelu Post</h3>
<p>Model <code>Post</code> implementuje trait <code>Searchable</code> z Laravel Scout. Trzy metody kontrolują sposób indeksowania:</p>

<p><strong><code>toSearchableArray()</code></strong> — buduje dokument przesyłany do Meilisearch. Tłumaczenia (PL + EN) są spłaszczane do jednej struktury płaskiej, co pozwala na wyszukiwanie w obu językach jednocześnie:</p>

<pre><code>public function toSearchableArray(): array
{
    $this->loadMissing(['translations', 'categories', 'tags']);
    return [
        'id'           => $this->id,
        'slug'         => $this->slug,
        'title'        => $this->translations->pluck('title')->join(' | '),
        'excerpt'      => $this->translations->map(fn ($t) => Str::limit(strip_tags($t->excerpt ?? ''), 300))->join(' | '),
        'content'      => Str::limit(strip_tags($this->translations->pluck('content')->join(' ')), 3000),
        'categories'   => $this->categories->pluck('name')->toArray(),
        'tags'         => $this->tags->pluck('name')->toArray(),
        'cover_image'  => $this->cover_image,
        'published_at' => $this->published_at?->timestamp,
    ];
}</code></pre>

<p><strong><code>shouldBeSearchable()</code></strong> — gate kontrolujący, które posty wchodzą do indeksu. Do Meilisearch trafiają wyłącznie posty opublikowane, z <code>published_at &lt;= now()</code> i bez soft delete:</p>

<pre><code>public function shouldBeSearchable(): bool
{
    return $this->status === 'published'
        && $this->published_at !== null
        && $this->published_at &lt;= now()
        && is_null($this->deleted_at);
}</code></pre>

<p><strong><code>makeAllSearchableUsing()</code></strong> — eager-loading relacji przy masowym imporcie, aby uniknąć N+1 przy <code>php artisan scout:import</code>.</p>

<h3>Konfiguracja scout.php</h3>
<p>W <code>config/scout.php</code> zdefiniowane zostały ustawienia per-indeks dla <code>posts</code>:</p>
<ul>
  <li><strong>searchableAttributes</strong>: <code>title</code>, <code>excerpt</code>, <code>content</code>, <code>categories</code>, <code>tags</code> — kolejność wpływa na ranking wyników.</li>
  <li><strong>filterableAttributes</strong>: <code>published_at</code> — umożliwia filtrowanie po dacie po stronie Meilisearch.</li>
  <li><strong>sortableAttributes</strong>: <code>published_at</code>.</li>
  <li><strong>typoTolerance</strong>: 1 literówka dla słów ≥5 znaków, 2 literówki dla słów ≥9 znaków.</li>
  <li><strong>rankingRules</strong>: domyślny zestaw Meilisearch (<code>words → typo → proximity → attribute → sort → exactness</code>).</li>
</ul>

<h3>Asynchroniczne aktualizacje indeksu</h3>
<p>Laravel Scout obsługuje kolejkowanie operacji indeksowania przez zmienną środowiskową <code>SCOUT_QUEUE=true</code>. Po jej ustawieniu dodanie lub usunięcie dokumentu z indeksu jest odkładane do kolejki Laravel (np. Redis), a nie wykonywane synchronicznie w trakcie żądania HTTP. To szczególnie istotne przy zapisywaniu postów przez panel admina — odpowiedź HTTP nie musi czekać na zakończenie operacji w Meilisearch.</p>

<p>Gdy post zmienia status (np. z <code>draft</code> na <code>published</code>), <code>PostObserver</code> nasłuchuje na zdarzenie <code>updated</code> i — przez mechanizm Scout — automatycznie kolejkuje re-indeksację. Zmiana statusu publikuje też zdarzenie do RabbitMQ (wymiana <code>blog</code>), informując inne serwisy o nowym poście.</p>

<p>Pełną re-indeksację wszystkich postów można wymusić poleceniem:</p>
<pre><code>php artisan scout:import "App\Models\Post"</code></pre>

<h3>SearchController</h3>
<p>Endpoint <code>GET /api/search?q=…</code> odpytuje jednocześnie trzy indeksy: <code>posts</code> (limit 5, z podświetlaniem <code>&lt;mark&gt;</code>), <code>categories</code> (limit 3) i <code>tags</code> (limit 5). W razie niedostępności Meilisearch kontroler łapie wyjątek i zwraca status 503 z pustymi kolekcjami wyników, dzięki czemu frontend nie traci działania.</p>
HTML,
            ]);

            $post2->categories()->attach($architecture->id);
        }

        // -----------------------------------------------------------------------
        // 5. Usuń puste kategorie (liście bez postów) i tagi bez postów
        // -----------------------------------------------------------------------
        // Kategorie: tylko liście (bez dzieci) bez przypisanych postów
        Category::whereDoesntHave('posts')
            ->whereDoesntHave('children')
            ->delete();

        // Tagi: bez przypisanych postów
        Tag::whereDoesntHave('posts')->delete();
    }

    public function down(): void
    {
        Post::where('slug', 'wyszukiwarka-blog-meilisearch')->forceDelete();
        Post::where('slug', 'wdrozenie-meilisearch-docker-compose-kubernetes')->forceDelete();
        Category::where('slug', 'dev-log')->delete();
        Category::where('slug', 'architektura')->delete();
    }
};
