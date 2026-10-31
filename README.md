# Git Widget for WordPress

I wanted my GitHub activity on my own website without screenshots that go stale the day after I take them, so I built this. Git Widget pulls your profile, contribution calendar, charts, achievements and more straight from GitHub and drops them into any page. It works as a Beaver Builder module or a simple `[git_widget]` shortcode, and Gutenberg and Elementor are next on the list.

## What it Does

Pick any of these sections, in any order, in one widget or spread across several:

- **Profile:** your photo, name, bio, company, location, website, followers and repositories
- **Organisations:** the orgs you publicly belong to, as little logo chips
- **Contribution calendar:** the familiar green squares, in your accent colour, with empty days left transparent so it looks right in dark mode
- **Activity overview:** a set of tiles you choose (see below) for the last 12 months, a specific year, or all time
- **Contribution types:** commits vs pull requests vs issues vs code reviews, as GitHub's cross chart or a bar
- **Contributions per month** and **per year:** bar charts
- **Contributions over time:** a running total, because watching a line go up is very satisfying
- **Activity by day of the week:** find out if you really are a Thursday person
- **Top languages:** a donut chart in GitHub's own language colours
- **Top repositories:** cards with stars, forks, language and last update
- **Achievements:** your badges, tiers and all (x2, x3...)

Every widget is independent. Put your bio card in the sidebar and your activity charts in the main column, each with its own settings.

## How to Install

1. Download the latest zip and upload it under **Plugins > Add New > Upload Plugin**.
2. Go to **Settings > Git Widget** and enter your GitHub username.
3. Optional: add a GitHub token. A fine-grained token with no permissions at all is enough, since it only reads public data. It gives exact contribution type counts and yearly totals. Everything else works without one.

GitHub data is cached (6 hours by default) so your pages stay quick. If you want fresh numbers right now, hit **Refresh GitHub data now**.

## Beaver Builder

Drag in the **GitHub Widget** module (you'll find it under *Git Widget*).

- **Content:** sections, username (it uses the one from settings if you leave it blank), heading, the View on GitHub switch, how underscores in your bio are handled, activity overview metrics and time frame, year, and how many repositories or languages to list
- **Style:** accent colour, background (match the page, light card or dark card), stacked or side-by-side layout, and the contribution types chart style

## Shortcode

Same options, typed out:

```text
[git_widget sections="profile,organisations"]
[git_widget sections="overview,calendar" theme="dark" accent="#8250df" title="This year on GitHub"]
[git_widget sections="overview" metrics="contributions,commits,merged_prs,stars,forks" period="all"]
[git_widget sections="languages,repositories" count="4"]
```

| Attribute | Default | Values |
|---|---|---|
| `sections` | `profile` | Comma-separated: `profile`, `organisations`, `calendar`, `overview`, `types`, `monthly`, `yearly`, `cumulative`, `weekdays`, `languages`, `repositories`, `achievements` |
| `user` | from settings | Any GitHub username |
| `title` | none | A heading above the widget |
| `year` | last 12 months | e.g. `2025` |
| `period` | last 12 months | `last`, `year` or `all` (activity overview) |
| `metrics` | contributions, active days, streaks | See the list below |
| `years` | `6` | Years in the yearly chart (1 to 20) |
| `count` | `6` | Repositories or languages to list |
| `accent` | `#2da44e` | Any hex colour |
| `theme` | `auto` | `auto`, `light` or `dark` |
| `layout` | `stack` | `stack` or `grid` |
| `types_style` | `axes` | `axes` (cross chart) or `bar` |
| `bio_breaks` | `yes` | `yes` turns runs of underscores in your bio into a line break, `no` shows them as typed |
| `show_link` | `yes` | `yes` or `no` |

## Activity Overview Metrics

Mix and match any of these:

`contributions`, `active_days`, `current_streak`, `longest_streak`, `daily_average`, `busiest_day`, `commits`, `pull_requests`, `merged_prs`, `issues_opened`, `reviews`, `stars`, `forks`, `repositories`, `languages`, `followers`, `following`, `organisations`, `member_since`

Stars, forks, repositories, languages, followers, following, organisations and years on GitHub are always today's numbers. The rest follow the time frame you pick.

## A Note on Bios

GitHub bios are one long line, so a lot of us pad them with underscores to push text onto the next line. Git Widget treats any run of two or more underscores (`__` or `___________`) as a single line break. If you actually want the underscores, switch it off.

## How the Data is Fetched

- Profile, organisations and repositories: the GitHub REST API
- Calendar: GitHub GraphQL when you have a token, otherwise GitHub's public contributions page
- Commit, pull request, issue and review counts: GitHub search, which works without a token
- Contribution type percentages without a token: the Activity overview on your GitHub profile (turn it on under **GitHub > Settings > Public profile**)
- Achievements: your public profile page, since GitHub doesn't have an API for them

## Development

Want to poke at it without a WordPress install? Run `php dev/preview.php > preview.html` and open it. You'll get every section rendered with sample data, side by side on a light and a dark background.

## Feedback

Made by [@DannoPeters](https://github.com/DannoPeters) in 🇨🇦. Suggestions and bug reports are very welcome, either as an [issue](https://github.com/DannoPeters/WordPress_Git-Widget/issues) or through [DannoPeters.ca](https://dannopeters.ca).

## License

GPL-2.0-or-later.
