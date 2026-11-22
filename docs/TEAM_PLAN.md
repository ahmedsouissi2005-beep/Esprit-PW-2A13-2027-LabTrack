# LabTrack – Team plan, User management module (class 2A13)

Current validation: user CRUD in MVC, roles and access control, security and sessions workshop, PHP + JS validation, GitHub teamwork. Each member owns one part: they explain it in the demo and push their own commits on it.

| Member | Part | Main files | Branch |
|---|---|---|---|
| **Ahmed** | Security & sessions, roles & access control, admin CRUD logic, GitHub (reviews and merges) | `index.php`, `config/routes.php`, `AuthController.php` (CSRF, `login` session part, `enforceSingleSession`, anti brute force, `requireAuth` / `requireRole`, `adminCreateUser` / `adminUpdateUser` / `adminDeleteUser`, `generateUniqueCode`), `AdminController::userForm`, `docs/SECURITE.md`, `README.md` | `feature/security-sessions` |
| **Aziz** | Database & model | `database.sql`, `config/database.php`, `models/UserModel.php` | `feature/user-database` |
| **Ismail** | Register, login, logout pages | `views/front/login.php`, `views/front/register.php`, `AuthController::register` / `loginPage` / `logoutAction` | `feature/user-auth` |
| **Fadi** | Profile & patient space | `views/front/profile.php`, `views/front/user_space.php`, `AuthController::updateProfile` / `changePassword` | `feature/user-profile` |
| **Fatma** | Back office pages | `views/back/pages/users.php`, `user_form.php`, `dashboard.php`, `assets/back/js/users.js` | `feature/user-backoffice` |
| **Yassine** | Validation & translations | `assets/js/form-validation.js`, `validationAttrs()` in `views/helpers.php`, `validateAccountFields`, `config/lang/fr.php`, `ar.php`, `en.php` | `feature/user-validation` |

## What each member explains in the demo

- **Ahmed**: SQL injection, XSS, CSRF, session fixation and hijacking, 30-minute timeout, one login at a time, brute-force lock, patient vs admin access (403), and the request flow route → controller → model → view.
- **Aziz**: the `utilisateur` table (UNIQUE email and code, bcrypt hash, `session_token`), the PDO singleton, getters/setters.
- **Ismail**: sign-up (role always patient, unique #code), `password_hash` / `password_verify`, the generic error message, logout that destroys the session.
- **Fadi**: change name, language and password (current password required, new session id afterwards), the personal #code shown only to its owner.
- **Fatma**: the users table, search, role filter, add/edit/delete forms, dashboard statistics.
- **Yassine**: why validate twice (JS for comfort, PHP for security), the rules and regex, messages in 3 languages, Arabic right-to-left.

## Git workflow (everyone)

```bash
git clone https://github.com/ahmedsouissi2005-beep/Esprit-PW-2A13-2027-LabTrack.git
cd Esprit-PW-2A13-2027-LabTrack
git config user.name  "Your Name"
git config user.email "your-github-email"
git checkout -b feature/your-part
# ... work on YOUR files only ...
git add .
git commit -m "feat(profile): show last password change date"
git push -u origin feature/your-part
```

Then open a **Pull Request** to `main`. Ahmed reviews and merges.

- Never push directly to `main`.
- Small commits, one change each: `feat(...)`, `fix(...)`, `style(...)`, `docs(...)`.
- Run `git pull origin main` in your branch before opening the PR.

## Project Board (GitHub → Projects)

Columns: **To do → In progress → Review → Done**. Each member adds 2–3 cards for their part, assigns them to themselves, and moves them as they work.
