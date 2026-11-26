# Family space (Espace famille) – module 6 (Ahmed)

One person follows the health of the whole family from one account, and **each adult decides what they share**.

## Features

| # | Feature | Where |
|---|---|---|
| 1 | **Family group**: create, rename, delete. The creator is the **manager** | `?page=family`, Members tab |
| 2 | **Invitation with name + private code only** (`Samia #0427`). The invited person accepts or refuses. Valid **48 hours**, then expires | Members tab, `?page=family` (received invitations) |
| 3 | **Consent**: when accepting, the person ticks what the family can see (blood tests, medical record, medicines, appointments). Changeable at any time | Received invitation, "What I share" |
| 4 | **Roles**: manager (invites, removes, dependents), caregiver (helps dependents, gets alerts), member (sees what is shared). Giving "manager" to someone transfers the role | Members tab |
| 5 | **Dependent profiles**: child or elder without an account. Later linked to their own account with an invitation (they take over the profile and choose what they share) | `?page=family_member` |
| 6 | **Dashboard**: one card per member (next appointment, today's medicines, what they share) | Overview tab |
| 7 | **Messages**: private family chat | Messages tab |
| 8 | **Gentle alerts**: medicine not ticked yet, appointment within 48 h, out-of-range test result, pending invitations | Overview tab |
| 9 | **Emergency card + QR code**: blood group, allergies, diseases, treatments, emergency contact. Print / PDF. The QR code opens a public page with **only** this information; it can be renewed or disabled | `?page=family_card`, `?page=urgence&t=…` |
| 10 | **Statistics**: medicines taken vs missed over 7 days, per member | Statistics tab |
| 11 | **Email notification** of the invitation (in the invited person's language) | `config/Mailer.php` |
| 12 | **Family calendar**: everyone's appointments and medicine times, one colour per member | Calendar tab |
| 13 | **Back office**: families, members, statistics, delete a family or remove a member, **never any health data** | `?page=admin_families` |

## Security

- **Access control on every page**: the family must exist **and** the logged-in account must belong to it. Otherwise the same "not found" answer (no way to discover other families). Every action also checks the role (manager / caregiver / member).
- **Invitation by private code**: name + code must match together; **same error message** for "unknown code" and "wrong name". **5 wrong codes in 15 minutes** block invitations for 15 minutes (table `famille_tentative`). An invitation shows no data: the person must accept.
- **Consent**: other accounts' data is read **only** if the owner ticked it. Your own data is always visible to you. Dependent profiles are visible to their family.
- **QR code**: 128-bit random token (`random_bytes(16)`), page `noindex` and `no-referrer`, can be renewed (old QR stops working) or disabled. The QR code is generated in PHP as SVG (`config/QrCode.php`), with no external library or script, so it is compatible with the site's CSP.
- **Usual protections**: prepared statements, `e()` on every output, CSRF token on every POST form, PHP + JS validation, messages in 3 languages.
- **Admin**: sees family names, members and roles, never blood tests, medicines, appointments, emergency cards or message contents.

## Data needed from the other modules

The family space **does not store** appointments, medicines or test results: it **reads** the tables of the other modules, **only if they exist** (otherwise it shows "coming soon"). Exact SQL in [`family_modules_contract.sql`](family_modules_contract.sql).

**Common rule:** each row belongs to either an account (`user_id`) **or** a dependent profile (`membre_id` → `famille_membre.id`). One of the two is filled in, the other is `NULL`. Without `membre_id`, a grandmother or child without an account cannot have appointments or medicines.

| Member | Table | Columns read by the family space | Used for |
|---|---|---|---|
| Aziz – Reminders & visits | `rendez_vous` | `user_id`, `membre_id`, `titre`, `date_rdv`, `lieu`, `medecin` | Calendar, upcoming list, alerts, member cards |
| Aziz – Reminders & visits | `rappel_medicament` | `user_id`, `membre_id`, `medicament`, `dose`, `heure`, `date_debut`, `date_fin` | Calendar, statistics, alerts |
| Aziz – Reminders & visits | `prise_medicament` | `rappel_id`, `date_prise`, `statut` ('pris' / 'oublie') | Statistics (taken vs missed), "not ticked yet" alert |
| Ismail – Blood tests | `bilan_sanguin` | `user_id`, `membre_id`, `titre`, `date_bilan`, `hors_norme` (0/1) | Calm alert to caregivers |
| Fadi – Health news & alerts | `campagne_sante` | `titre`, `public` ('tous' / 'enfants' / 'aines'), `date_debut`, `date_fin` | Campaigns shown to families with children / elders |
| Fatma – Medical record | *(none required)* | The emergency card has its own table (`fiche_urgence`); "Medical record" sharing controls who can see it | Emergency card |
| Yassine – "What if…" | *(none required)* | Can later read the family's shared data for family scenarios | – |

You can add any other columns. Just keep these names, or tell Ahmed if you need to change them.

## Tables of the module

`famille`, `famille_membre`, `famille_invitation`, `famille_message`, `fiche_urgence`, `famille_tentative`. They are in `database.sql` and are also created automatically the first time the Family page is opened (`FamilyController::ensureTables()`).

## Test it quickly

1. Create two patient accounts (A and B). B reads his private code in "My space".
2. A: **Family** → create "Famille Test" → Members tab → invite B with his exact name + code.
3. B: **Family** → the invitation appears → tick what to share → Accept.
4. A: add a dependent profile (child), open his **emergency card**, fill it in, **Create the QR code**, then open the link shown under it in a private window (no login needed).
5. To see the calendar and statistics before the other modules exist: run `docs/family_modules_contract.sql` in phpMyAdmin, then add a few rows to `rendez_vous` / `rappel_medicament` / `prise_medicament` for A, B or the dependent (`membre_id`).
6. Wrong code 5 times in the invitation form → "Too many wrong codes".
7. Admin → **Family space** in the sidebar: list, statistics, members, no health data.
