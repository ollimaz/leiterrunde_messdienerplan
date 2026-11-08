# Messdienerplan (altar server plan)

Module of the Leiterrunde suite – requires the `leiterrunde` core app.

Plan altar server duties for church services across multiple locations
(Kirchorte). Planners create planning periods, add services (optionally as
weekly/biweekly/monthly recurring series), and invite persons. Invited persons
mark themselves available or blocked per date while the poll is open. When the
planner closes the period, a smart assignment distributes duties fairly:
only "yes" answers are candidates, manual assignments are kept, nobody is
booked twice on the same date, and scarce services are filled first.

Capabilities:

- `messdiener.read` – view plan and own duties (default: any Leiterrunde role)
- `messdiener.manage` – manage locations, periods, services, invites, availability of others, run assignment, statistics (default: leiter, admin)

Self-service availability does not require a Leiterrunde role: any logged-in
user whose person record is invited to an open period can answer.
