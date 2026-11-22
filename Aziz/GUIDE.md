# LabTrack – Aziz: push your part (Database & model)

Repo: https://github.com/ahmedsouissi2005-beep/Esprit-PW-2A13-2027-LabTrack
Your branch: **`feature/user-database`** · created from **`main`** · Pull Request to **`main`**

Note: the old repo is now called `LabTrack-old`. Your old branch and PR #2 stay there; don't use them anymore.

## What is in this folder

| File in `files/` | Goes to (same path in the repo) |
|---|---|
| `database.sql` | `database.sql` |
| `config/database.php` | `config/database.php` |
| `models/UserModel.php` | `models/UserModel.php` |

Keep the brute-force columns (`failed_attempts`, `locked_until`) and `session_token` in `database.sql`: the security part needs them.

## Step 0 – Once

1. Accept the GitHub invitation from **ahmedsouissi2005-beep** (your email or https://github.com/notifications).
2. In a terminal:

```bash
git clone https://github.com/ahmedsouissi2005-beep/Esprit-PW-2A13-2027-LabTrack.git
cd Esprit-PW-2A13-2027-LabTrack
git config user.name  "Your Name"
git config user.email "the-email-of-your-github-account"
```

## Step 1 – Your branch

```bash
git checkout main
git pull origin main
git checkout -b feature/user-database
```

## Step 2 – Add your files (2 commits)

Copy the content of `files/` into the repo folder (keep the same folders).

```bash
git add database.sql
git commit -m "feat(database): utilisateur table with unique email and code, bcrypt password, session token and brute-force columns"

git add config/database.php models/UserModel.php
git commit -m "feat(model): PDO singleton connection and UserModel with getters and setters"
```

## Step 3 – Push and open the Pull Request

```bash
git push -u origin feature/user-database
```

On GitHub: **Compare & pull request** → base: **`main`** ← compare: **`feature/user-database`** → **Create pull request**.
Ahmed reviews and merges.

## In the demo you explain

- The `utilisateur` table: UNIQUE email and code, bcrypt hash, `session_token`, `failed_attempts`, `locked_until`.
- Why PDO with prepared statements (no SQL injection), and the singleton (one connection).
- `UserModel`: private attributes, getters/setters, `fromArray()`.
