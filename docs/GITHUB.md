# Publication sur GitHub (standard Esprit)

À faire une fois par le groupe, en suivant la directive « Standardisation des dépôts GitHub ».

## 1. Nom du dépôt (0,5 point)

Format : `Esprit-[PI]-[Classe]-[AU]-[NomDuProjet]`

```
Esprit-PW-2A13-2027-LabTrack
```

Classe : `2A13`. `2027` correspond à l'année universitaire 2026–2027, sur le modèle de l'exemple officiel (`Esprit-PIDEV-3A10-2026-TaskManager` pour 2025–2026).

## 2. Description du dépôt (0,5 point)

Dans GitHub : bouton ⚙️ à côté de **About**, champ **Description** :

```
LabTrack - web health platform (blood tests explained, medical record, reminders, family space) in Arabic, French and English. Developed at Esprit School of Engineering - Tunisia, Academic Year 2026-2027. PHP, MySQL, MVC, Bootstrap.
```

## 3. Topics (0,5 point)

Même fenêtre, champ **Topics** :

```
esprit-school-of-engineering academic-project esprit-pw 2026-2027 php mysql mvc bootstrap health
```

## 4. README (0,25 point, ou 0,5 sans hébergement)

Le `README.md` suit la structure demandée (Overview, Features, Tech Stack, Architecture, Contributors, Academic Context, Getting Started, Acknowledgments) et contient le mot-clé **Esprit School of Engineering**. Complétez la section **Contributors** avec vos noms GitHub.

## 5. Envoyer le projet

```bash
git init
git add .
git commit -m "Initial commit: LabTrack user management module"
git branch -M main
git remote add origin https://github.com/<organisation>/Esprit-PW-2A13-2027-LabTrack.git
git push -u origin main
```

Ensuite, après chaque modification :

```bash
git add .
git commit -m "Description de la modification"
git push origin main
```

## 6. Hébergement (facultatif, 0,25 point)

LabTrack utilise PHP et MySQL : GitHub Pages ne convient pas (sites statiques uniquement). Parmi les hébergeurs recommandés, Render, Railway ou DigitalOcean (avec le GitHub Student Pack) permettent d'héberger PHP + MySQL.
