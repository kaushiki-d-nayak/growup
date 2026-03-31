# Before I Grow Up 🌱
### Turning childhood dreams into real achievements

**Before I Grow Up** is a purpose-driven web platform where children can share their dreams, guardians can guide them, and supporters can help make those dreams come true.

Built with care, structure, and real-world workflows, this project connects hope with action.

🔗 **Live Website:** [https://beforeigrowup.online/](https://beforeigrowup.online/)

---

## 📸 Screenshots

<img width="3799" alt="Home Page" src="https://github.com/user-attachments/assets/76400ce8-967c-4672-9dc3-8f0e52b15ae8" />
<img width="3805" alt="Dream Submission" src="https://github.com/user-attachments/assets/b871177f-e2d3-4093-8a14-bdec3af71520" />
<img width="3806" alt="Browse Dreams" src="https://github.com/user-attachments/assets/76cb17d9-c166-4344-8b62-3be732b2a9b0" />
<img width="3797" alt="Admin Dashboard" src="https://github.com/user-attachments/assets/06ad7196-3d30-4a1d-b12d-b14ef21a29c5" />
<img width="3808" alt="Footer" src="https://github.com/user-attachments/assets/de7677d4-3f14-4a53-9773-c5ac427d5763" />

---

## 🌟 Key Features

### 👶 Children & Guardians
- Secure registration and login system
- Dream submission with structured details
- Guardians can track and manage submitted dreams
- Status updates as dreams move from **idea → adoption → achievement**

### 🤝 Supporters
- Browse verified dreams
- Adopt a dream and support its completion
- Confirm dream achievement
- Submit feedback after completion

### 🛠 Admin Panel
- Centralized dashboard for platform control
- Verify or reject submitted dreams
- Approve or reject adoption requests
- Manage users across all roles
- View matched dream–supporter pairs
- Review feedback and completion reports
- Trigger automated email notifications

### 📧 Email Automation
- Registration confirmations
- Dream approval or rejection updates
- Adoption confirmations
- Achievement and feedback emails
- Powered by **PHPMailer**

---

## 🧰 Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | HTML5, CSS3, JavaScript |
| Backend | PHP (Core PHP, role-based architecture) |
| Database | MySQL |
| Email | PHPMailer (SMTP-based notifications) |
| Deployment | Live production server, domain-connected |

---

## 📂 Project Structure

```
Before-I-Grow-Up/
├── admin/
│   ├── dashboard.php
│   ├── manage_users.php
│   ├── manage_dreams.php
│   ├── manage_adoptions.php
│   ├── matched_pairs.php
│   └── feedback_reviews.php
│
├── guardian/
│   ├── submit_dream.php
│   └── my_dreams.php
│
├── supporter/
│   ├── browse_dreams.php
│   ├── adopt_dream.php
│   ├── confirm_dream_achievement.php
│   └── feedback_form.php
│
├── config/
│   ├── app.php
│   └── database.php
│
├── includes/
│   ├── auth.php
│   ├── validation.php
│   ├── mail.php
│   ├── header.php
│   ├── footer.php
│   ├── admin_sidebar.php
│   ├── dreams_schema.php
│   ├── dream_feedback.php
│   └── dream_achievement.php
│
├── css/
│   └── style.css
│
├── PHPMailer/
│   └── (PHPMailer library files)
│
├── index.php
├── login.php
├── register.php
├── logout.php
├── forgot_password.php
├── reset_password.php
├── favicon.svg
└── README.md
```

---

## 🚀 How It Works

```
1. A child (via guardian) submits a dream
         ↓
2. Admin verifies the dream
         ↓
3. Supporters browse and adopt dreams
         ↓
4. Dream gets fulfilled
         ↓
5. Supporter confirms achievement
         ↓
6. Feedback is collected
         ↓
7. Admin reviews and archives completion
```

> Every step is **authenticated**, **validated**, and **logged**.

---

## 🛡 Security & Validation

- Session-based authentication
- Role-based access control (**Admin / Guardian / Supporter**)
- Centralized input validation
- Protected admin routes
- Secure password reset flow

---

## 👩‍💻 Author

**Kaushiki**
GitHub: [@kaushiki-d-nayak](https://github.com/kaushiki-d-nayak)

---

<div align="center">

*Every child has a dream.*
**Before I Grow Up helps turn those dreams into milestones. 🌟**

</div>