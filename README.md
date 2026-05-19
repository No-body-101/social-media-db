# Nexus — Social Media Database Management System

## Group Information
**Group Number:** 13

| Name | Roll Number |
|------|-------------|
| Munawar Ali | 24P-0621 |
| Haris | 24P-0525 |

---

## Project Title & Description
**Nexus** is a full-stack social media web application built as a database management system project. It allows users to register, create posts, follow other users, like and comment on posts, send direct messages, and receive notifications — all backed by a relational MySQL database.

---

## GitHub Repository
🔗 [https://github.com/No-body-101/social-media-db](https://github.com/No-body-101/social-media-db)

---

## Technologies Used

| Layer | Technology |
|-------|------------|
| Frontend | HTML, CSS, JavaScript (AJAX) |
| Backend | PHP 8.2 |
| Database | MySQL 8.0 |
| Server | Apache (via XAMPP) |
| Version Control | Git & GitHub |

---

## Database Tables
- `user` — stores user accounts, bios, and profile pictures
- `posts` — stores posts with text and media
- `likes` — tracks which users liked which posts
- `comments` — stores comments on posts
- `follows` — tracks follower/following relationships
- `messages` — stores direct messages between users
- `notifications` — stores like, comment, and follow alerts
- `hashtags` — stores hashtag names and usage counts

---

## Installation & Setup

### Prerequisites
- [XAMPP](https://www.apachefriends.org/download.html) (includes Apache, MySQL, PHP)
- [Git](https://git-scm.com/)

### Step 1 — Clone the Repository
Open a terminal and run:
```bash
git clone https://github.com/No-body-101/social-media-db.git
```

### Step 2 — Move to XAMPP
Move the cloned `social-media-db` folder into your XAMPP htdocs directory:
```
C:/xampp/htdocs/social-media-db/
```

### Step 3 — Start XAMPP
1. Open **XAMPP Control Panel**
2. Click **Start** next to **Apache**
3. Click **Start** next to **MySQL**

### Step 4 — Set Up the Database
1. Open your browser and go to: `http://localhost/phpmyadmin`
2. Click **New** and create a database named `social_media`
3. Select the `social_media` database
4. Click **Import** → choose the file `sql/setup.sql` from the project folder
5. Click **Go**

### Step 5 — Configure Database Connection
Open `db.php` and make sure the settings match your XAMPP setup:
```php
$host = 'localhost';
$db   = 'social_media';
$user = 'root';
$pass = ''; // default XAMPP password is empty
```

### Step 6 — Run the Application
Open your browser and go to:
```
http://localhost/social-media-db/register.php
```
Create an account and start using Nexus!

---

## Features
- ✅ User registration and login with hashed passwords
- ✅ Create, view, and delete posts (text + image/video)
- ✅ Public and private post visibility
- ✅ Like and unlike posts (AJAX — no page reload)
- ✅ Comment on posts (AJAX — no page reload)
- ✅ Follow and unfollow users
- ✅ User profile pages with profile picture and banner
- ✅ Direct messaging between users
- ✅ Notifications for likes, comments, and follows
- ✅ Search for users by username or bio
- ✅ Suggested users to follow
