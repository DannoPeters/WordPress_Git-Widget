# Git Widget for WordPress

Embed your GitHub activity anywhere on a WordPress site: profile and bio, organisations, the contribution calendar, an activity overview, contribution types, monthly and yearly charts, and achievements.

Use it as a **Beaver Builder module** or the **`[git_widget]` shortcode**. Gutenberg and Elementor support are planned (see the v0.2 milestone).

## Sections

| Key | Shows | Needs a token? |
|---|---|---|
| `profile` | Photo, name, username, bio, company, location, website, followers, following, public repositories | No |
| `organisations` | Public organisations | No |
| `calendar` | Contribution calendar for the last 12 months or a chosen year | No |
| `overview` | Tiles of your choice (see below) for the last 12 months, a year, or all time | No |
| `types` | Commits vs pull requests vs issues vs code reviews: GitHub's cross chart or a bar. Exact counts with a token; otherwise the profile's percentages (needs Activity overview turned on in GitHub) | No |
| `monthly` | Contributions per month (bar chart) | No |
| `yearly` | Contributions per year (bar chart) | Recommended |
| `achievements` | Achievement badges with tiers | No |
| `languages` | Top languages across public repositories (donut, GitHub's language colours) | No |
| `repositories` | Top repositories by stars, as cards | No |
| `weekdays` | Contributions by day of the week | No |
| `cumulative` | Running total of contributions over the period | No |

Mix any of them in one widget, or spread them across several widgets on the same page. Each widget is independent.

## Setup

1. Install and activate the plugin.
2. Go to **Settings → Git Widget** and enter your GitHub username.
3. Optional: add a GitHub token. A fine-grained token with **no permissions** is enough; it only reads public data. It unlocks contribution types and makes the yearly chart exact.

GitHub data is cached (6 hours by default). **Refresh GitHub data now** clears the cache.

## Beaver Builder

Add the **GitHub Widget** module (category *Git Widget*).

- **Content:** sections (in the order you pick them), username (defaults to the one in settings), heading, year, years in the yearly chart
- **Style:** accent colour, background (match the page, light card or dark card), stacked or side-by-side layout, View on GitHub link

## Shortcode

```text
[git_widget sections="profile,organisations"]
[git_widget sections="overview,calendar" theme="dark" accent="#8250df" title="This year on GitHub"]
[git_widget sections="monthly" year="2025"]
[git_widget sections="yearly" years="8" user="octocat" show_link="no"]
```

| Attribute | Default | Values |
|---|---|---|
| `sections` | `profile` | Comma-separated keys from the table above |
| `user` | settings | GitHub username |
| `year` | last 12 months | e.g. `2025` |
| `years` | `6` | 1–20 |
| `accent` | `#2da44e` | Any hex colour |
| `theme` | `auto` | `auto`, `light`, `dark` |
| `layout` | `stack` | `stack`, `grid` |
| `title` | none | Heading text |
| `show_link` | `yes` | `yes`, `no` |
| `types_style` | `axes` | `axes` (cross chart), `bar` |
| `bio_breaks` | `yes` | `yes` turns runs of 2+ underscores in the bio into one line break; `no` shows them |
| `count` | `6` | Repositories or languages to list |

## Activity overview metrics

Pick any of these with `metrics="..."` (or the Beaver Builder *Metrics* list), and the time frame with `period="last|year|all"`:

`contributions`, `active_days`, `current_streak`, `longest_streak`, `daily_average`, `busiest_day`, `commits`, `pull_requests`, `merged_prs`, `issues_opened`, `reviews`, `stars`, `forks`, `repositories`, `languages`, `followers`, `following`, `organisations`, `member_since`.

Stars, forks, repositories, languages, followers, following, organisations and years on GitHub are always current. Commit, pull request, issue and review counts come from GitHub search, which works without a token.

```text
[git_widget sections="overview" metrics="contributions,commits,merged_prs,stars,forks" period="all"]
```

## How the data is fetched

- Profile and organisations: GitHub REST API.
- Calendar: GitHub GraphQL with a token, otherwise GitHub's public contributions page.
- Contribution types and years: GitHub GraphQL (token).
- Achievements: the public profile page (GitHub has no API for them).

## Development

`php dev/preview.php > preview.html` renders every section with sample data, in a light and a dark container, without WordPress.

## License

GPL-2.0-or-later.
