# Changelog

## 0.3.0 (2026-11-01)

- Simpler Beaver Builder settings: each part of the widget now has its own Show / Hide switch, and a part's options only appear once it is turned on
- Options for a part you are not showing stay out of the way, so the panel only ever shows what is relevant
- Widgets saved before this update keep working unchanged

## 0.2.1 (2026-10-31)

- Licensed under GPL-3.0-or-later, with one additional term under section 7(b) (see ADDITIONAL-TERMS.md)
- Widgets carry data-gw and data-gw-edition attributes, and pages get a Git Widget generator tag
- The front-end script keeps the credit line readable in the free edition
- When something removes the credit, the administrator sees a dashboard notice that it is required

## 0.2.0 (2026-10-31)

**Free core and Git Widget Pro** (#28)

- The free plugin keeps every section and uses GitHub's own colours
- Pro features need the Git Widget Pro add-on: custom colours for every part, type and card styling, the visitor year picker, the joined all-years calendar and trend chart, and the overview extras. Without Pro those settings are marked (Pro) and quietly fall back, so pages never break
- A small "Git Widget by DannoPeters" line under each widget; Pro removes it
- New hooks for add-ons: gw_is_pro, gw_defaults, gw_atts, gw_widget_style, gw_widget_classes, gw_credit, gw_bb_form, gw_bb_atts
- Every colour in the stylesheet is now a CSS variable (--gw-c-*) with the GitHub colour as its fallback

## 0.1.10 (2026-10-31)

- Overview tile extras, each a separate switch: a tiny trend line, a change badge against the previous period, a highlighted tile (your best figure or one you pick) and links to GitHub (#26)

## 0.1.9 (2026-10-31)

- Small-screen options for the calendar: keep scrolling, or split it into two 6-month rows, joined (months above and below) or with the months between (#24)
- Small-screen options for the year list: multiple rows, one scrolling row (newest first) or a dropdown (#24)
- Activity overview per screen size: tiles per row, text size and full, short or no labels for desktop, tablet and mobile; mobile defaults to two per row with short names (#24)

## 0.1.8 (2026-10-31)

- All-time calendar defaults to one joined block: a band per year, weekdays on the left, months along the top and year labels on the right; separate years remain an option
- Optional chart above the calendar: contributions per year, month or day, as a line (shaded underneath by default) or bars (#22)

## 0.1.7 (2026-10-31)

- Year picker for visitors on the contribution calendar: a dropdown, or a list of years on the left, right or bottom (#20)

## 0.1.6 (2026-10-31)

- Contribution calendar: last 12 months, a specific year, or all time, which stacks one calendar per year on a shared colour scale (#18)

## 0.1.5 (2026-10-31)

- Awesome Mode: the activity overview picks your most impressive stats automatically (new default)
- Beaver Builder: an On/Off switch for every overview stat instead of a scrolling list (#16)

## 0.1.4 (2026-10-31)

- Empty calendar days are transparent with a faint outline, so they look right in dark mode (#13, #14)
- README rewritten and brought up to date (#15)

## 0.1.3 (2026-10-31)

- Activity overview: choose from 19 metrics (contributions, streaks, commits, pull requests opened and merged, issues opened, reviews, stars, forks, repositories, languages, followers, organisations, years on GitHub and more)
- Time frame for the overview: last 12 months, a chosen year, or all time

## 0.1.2 (2026-10-31)

- "View on GitHub" link is now an On/Off switch on the Content tab
- Beaver Builder settings show who made the plugin and where to send suggestions and bug reports

## 0.1.1 (2026-10-31)

- Contribution types work without a token (profile activity overview) and default to GitHub's cross chart
- Bio: runs of two or more underscores become one line break (option to turn off)
- New sections: top languages, top repositories, activity by weekday, running total

## 0.1.0 (2026-10-31)

- Settings page with username, optional token and cache time
- Sections: profile, organisations, contribution calendar, activity overview, contribution types, monthly and yearly charts, achievements
- [git_widget] shortcode and Beaver Builder module; multiple independent widgets per page
- Light/dark friendly styling with an accent colour, and forced light or dark cards
