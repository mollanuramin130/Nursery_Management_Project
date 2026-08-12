# Nursery Platform — Sample Login Credentials

Keep this file handy for local / staging API, website, and mobile testing.

**Important:** These accounts exist only after you load `nursery_sample_data.sql`.  
**Do not use these passwords in production.**

---

## Master password (all sample users)

```text
Secret@123
```

Login username = **email** address.

---

## All users

| ID | Name | Email (username) | Password | Role | Phone |
|----|------|------------------|----------|------|-------|
| 1 | Nursery Super Admin | `superadmin@nursery.test` | `Secret@123` | super_admin | 9000000001 |
| 2 | Ops Admin | `admin@nursery.test` | `Secret@123` | admin | 9000000002 |
| 3 | Asha Kumar | `asha@example.com` | `Secret@123` | customer | 9876543210 |
| 4 | Ravi Sharma | `ravi@example.com` | `Secret@123` | customer | 9876543211 |
| 5 | Meera Patel | `meera@example.com` | `Secret@123` | customer | 9876543212 |
| 6 | Kabir Singh | `kabir@example.com` | `Secret@123` | customer | 9876543213 |
| 7 | Sneha Reddy | `sneha@example.com` | `Secret@123` | customer | 9876543214 |
| 8 | Arjun Mehta | `arjun@example.com` | `Secret@123` | customer | 9876543215 |
| 9 | Priya Nair | `priya@example.com` | `Secret@123` | customer | 9876543216 |
| 10 | Vikram Joshi | `vikram@example.com` | `Secret@123` | customer | 9876543217 |
| 11 | Inventory Manager | `inventory@nursery.test` | `Secret@123` | inventory_manager | 9000000003 |
| 12 | Order Manager | `orders@nursery.test` | `Secret@123` | order_manager | 9000000004 |

---

## Quick copy list

```text
superadmin@nursery.test / Secret@123
admin@nursery.test      / Secret@123
asha@example.com        / Secret@123
ravi@example.com        / Secret@123
meera@example.com       / Secret@123
kabir@example.com       / Secret@123
sneha@example.com       / Secret@123
arjun@example.com       / Secret@123
priya@example.com       / Secret@123
vikram@example.com      / Secret@123
inventory@nursery.test  / Secret@123
orders@nursery.test     / Secret@123
```

---

## Suggested test accounts by purpose

| Purpose | Use this account |
|---------|------------------|
| Admin panel / full ops | `admin@nursery.test` / `Secret@123` |
| Super admin / roles | `superadmin@nursery.test` / `Secret@123` |
| Customer with cart + orders + wishlist | `asha@example.com` / `Secret@123` |
| Customer with shipped order | `ravi@example.com` / `Secret@123` |
| Customer with delivered medicinal order | `meera@example.com` / `Secret@123` |
| Customer with express OFD order | `kabir@example.com` / `Secret@123` |
| Customer with gift order processing | `sneha@example.com` / `Secret@123` |
| Customer with COD order | `priya@example.com` / `Secret@123` |
| Inventory staff | `inventory@nursery.test` / `Secret@123` |
| Order staff | `orders@nursery.test` / `Secret@123` |

---

## API login example

```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "asha@example.com",
  "password": "Secret@123"
}
```

---

## Related files

- `../database/nursery_sample_data.sql` — creates these users
- `DATABASE_DESIGN.md` — database docs (includes same table)
- `PROJECT_DEVELOPMENT_GUIDE.md` — API contracts
- `IMPLEMENTATION_PLAYBOOK.md` — build & ship steps

**Project root:** `/Users/nuramin/Desktop/Nursery_Platform/`
