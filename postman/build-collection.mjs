import { writeFileSync } from "node:fs";

const loginScript = [
  "const json = pm.response.json();",
  "if (json.token) {",
  "    pm.collectionVariables.set('token', json.token);",
  "    console.log('Saved token for the rest of the collection.');",
  "}",
];

function item(name, method, path, { body, query, description, auth = true, events } = {}) {
  const headers = [{ key: "Accept", value: "application/json" }];
  const request = {
    method,
    header: headers,
    url: `{{base_url}}${path}${query ? `?${query.map((q) => `${q.key}=${q.value}`).join("&")}` : ""}`,
    description,
  };

  if (body !== undefined) {
    headers.push({ key: "Content-Type", value: "application/json" });
    request.body = {
      mode: "raw",
      raw: JSON.stringify(body, null, 2),
      options: { raw: { language: "json" } },
    };
  }
  if (!auth) request.auth = { type: "noauth" };

  const entry = { name, request };
  if (events) entry.event = events;
  return entry;
}

function folder(name, description, items) {
  return { name, description, item: items };
}

const collection = {
  info: {
    name: "BhuriBhoj API",
    description:
      "Every BhuriBhoj API with a sample body.\n\n1. Import this file in Postman.\n2. Send Auth → Login. The token is saved on the collection automatically.\n3. Other requests send Authorization: Bearer {{token}}.\n\nbase_url defaults to the local API (http://127.0.0.1:8000/api). Change the collection variable for live: https://bhuribhoj-api.liwaas.com/api\n\nList fetches are POST. Filters go in the JSON body, not the query string.",
    schema: "https://schema.getpostman.com/json/collection/v2.1.0/collection.json",
  },
  variable: [
    { key: "base_url", value: "http://127.0.0.1:8000/api" },
    { key: "token", value: "" },
    { key: "user_id", value: "9" },
    { key: "record_id", value: "1" },
    { key: "invitation_token", value: "paste-invitation-token" },
  ],
  auth: {
    type: "bearer",
    bearer: [{ key: "token", value: "{{token}}", type: "string" }],
  },
  item: [
    folder("Auth", "Login saves the bearer token onto the collection.", [
      item("Login", "POST", "/auth/login", {
        auth: false,
        description: "Practice seats: president@paulmess.local / President@123, manager@paulmess.local / Manager@123, member@paulmess.local / Member@123, admin@bhuribhoj.local / Admin@123.",
        body: {
          email: "president@paulmess.local",
          password: "President@123",
          device_name: "postman",
        },
        events: [
          {
            listen: "test",
            script: { type: "text/javascript", exec: loginScript },
          },
        ],
      }),
      item("Me", "GET", "/auth/me", {
        description: "Current user. Avatar is included here and hidden on member lists.",
      }),
      item("Update profile", "POST", "/auth/profile", {
        description: "Password is optional. When set, password_confirmation is required. Avatar is a data URL: data:image/jpeg;base64,...",
        body: {
          name: "Paul President",
          mobile: "9000000001",
          password: "",
          password_confirmation: "",
        },
      }),
      item("Logout", "POST", "/auth/logout", {
        description: "Revokes the current token.",
        body: {},
      }),
    ]),
    folder("Plans", "Public. No token.", [
      item("Fetch plans", "POST", "/plans/fetch", {
        auth: false,
        description: "Active subscription plans.",
        body: {},
      }),
    ]),
    folder("Subscriptions", "Opens a new mess and returns a president token.", [
      item("Purchase", "POST", "/subscriptions/purchase", {
        auth: false,
        description: "plan_id comes from Plans → Fetch. mess.code is optional and must be unique.",
        body: {
          plan_id: 1,
          mess: {
            name: "New Mess",
            address: "12 College Road",
            district: "Kolkata",
            pincode: "700001",
            contact_no: "9800000000",
            email: "newmess@example.com",
            fund_mode: "mess_and_manager",
          },
          president: {
            name: "New President",
            email: "president@newmess.example",
            password: "President@123",
            mobile: "9800000001",
            room_no: "P1",
          },
          device_name: "postman",
        },
      }),
    ]),
    folder("Mess invitations", "Token routes are public. Fetch, create, and cancel are admin only.", [
      item("Fetch by token", "GET", "/mess-invitations/token/{{invitation_token}}/fetch", {
        auth: false,
        description: "Look up a pending invitation before the president accepts it.",
      }),
      item("Accept invitation", "POST", "/mess-invitations/token/{{invitation_token}}/accept", {
        auth: false,
        description: "Creates the mess and the president, then returns a token.",
        body: {
          mess: {
            name: "Invited Mess",
            address: "12 College Road",
            district: "Kolkata",
            pincode: "700001",
            contact_no: "9800000000",
            email: "invited@example.com",
            fund_mode: "manager_only",
          },
          president: {
            name: "Invited President",
            password: "President@123",
            mobile: "9800000002",
            room_no: "P1",
          },
          device_name: "postman",
        },
      }),
      item("Fetch invitations", "POST", "/mess-invitations/fetch", {
        description: "Admin. Laravel page is a query param.",
        body: {},
        query: [{ key: "page", value: "1" }],
      }),
      item("Create invitation", "POST", "/mess-invitations/create", {
        description: "Admin. expires_in_days is 1–30 and defaults to 7.",
        body: {
          plan_id: 1,
          email: "newpresident@example.com",
          mobile: "9800000099",
          mess_name: "Future Mess",
          expires_in_days: 7,
        },
      }),
      item("Cancel invitation", "DELETE", "/mess-invitations/{{record_id}}/cancel", {
        description: "Admin. Only a pending invitation can be cancelled.",
      }),
    ]),
    folder("Dashboard", "President, manager, member, and admin.", [
      item("Fetch dashboard", "POST", "/dashboard/fetch", {
        description: "Returns user, mess_totals (deposits, expenses, funds, balance), and my_totals.",
        body: {},
      }),
    ]),
    folder("Messes", "Fetch is any mess role. Update is president or admin.", [
      item("Fetch mess", "POST", "/messes/fetch", {
        description: "President, manager, and member receive their own mess. Admin receives a paginated list.",
        body: {},
      }),
      item("Update mess", "POST", "/messes/update", {
        description: "Send only the fields you want to change. fund_mode is manager_only or mess_and_manager.",
        body: {
          name: "Paul Mess",
          address: "12 College Road",
          district: "Kolkata",
          pincode: "700001",
          contact_no: "9800000000",
          email: "paulmess@example.com",
          fund_mode: "mess_and_manager",
          monthly_due_amount: 1500,
          monthly_due_day: 5,
          late_fee_amount: 50,
          guest_chicken_price: 80,
          guest_fish_price: 70,
          guest_egg_price: 20,
          guest_veg_price: 40,
        },
      }),
    ]),
    folder("Members", "Fetch is president or manager. Create and update are president.", [
      item("Fetch members", "POST", "/members/fetch", {
        description:
          "search matches name, email, mobile, member code, and a numeric id. role is president, manager, or member. status is active or inactive. room_no is exact. limit defaults to 10 (max 100). offset defaults to 0. Response: data, count, limit, offset, rooms.",
        body: {
          search: "paul",
          role: "member",
          status: "active",
          room_no: "12",
          limit: 10,
          offset: 0,
        },
      }),
      item("Create member", "POST", "/members/create", {
        description: "role defaults to member. Allowed roles: member, manager.",
        body: {
          name: "New Member",
          email: "new.member@paulmess.local",
          password: "Member@123",
          mobile: "9000000099",
          room_no: "16",
          role: "member",
          status: "active",
        },
      }),
      item("Update member", "POST", "/members/update", {
        description: "A president cannot mark their own seat inactive.",
        body: {
          user_id: 9,
          name: "Paul Member",
          mobile: "9000000003",
          room_no: "12",
          status: "active",
        },
      }),
    ]),
    folder("Managers", "President only. A month can have two managers.", [
      item("Fetch managers", "POST", "/managers/fetch", {
        description: "month defaults to the current YYYY-MM.",
        body: { month: "2026-10" },
      }),
      item("Assign manager", "POST", "/managers/assign", {
        body: { user_id: 9, month: "2026-10" },
      }),
      item("Remove manager", "DELETE", "/managers/{{record_id}}/remove", {
        description: "record_id is the assignment id from Fetch managers, not the user id.",
      }),
    ]),
    folder("Deposits", null, [
      item("Fetch deposits", "POST", "/deposits/fetch", {
        description: "A member only sees their own rows. Response: total_amount, data, count, limit, offset.",
        body: {
          user_id: 6,
          fund_type: "manager_fund",
          deposit_type: "monthly",
          for_month: "2026-10",
          limit: 20,
          offset: 0,
        },
      }),
      item("Create deposit", "POST", "/deposits/create", {
        description: "fund_type: mess_fund or manager_fund. deposit_type: join, monthly, or extra.",
        body: {
          user_id: 9,
          fund_type: "manager_fund",
          deposit_type: "extra",
          amount: 200,
          deposited_on: "2026-10-18",
          for_month: "2026-10",
          note: "Extra deposit",
        },
      }),
    ]),
    folder("Monthly dues", "Generate and mark-overdue: president or manager. Waive: president. Pay: the member can pay their own.", [
      item("Fetch dues", "POST", "/monthly-dues/fetch", {
        description: "status: pending, paid, overdue, waived. month defaults to the current month.",
        body: { month: "2026-10", status: "pending", user_id: 9 },
      }),
      item("Generate dues", "POST", "/monthly-dues/generate", {
        body: { month: "2026-10" },
      }),
      item("Pay due", "POST", "/monthly-dues/pay", {
        body: {
          due_id: 1,
          deposited_on: "2026-10-18",
          fund_type: "manager_fund",
          apply_late_fee: false,
          note: "Paid at the desk",
        },
      }),
      item("Waive due", "POST", "/monthly-dues/waive", {
        body: { due_id: 1, note: "Waived by the president" },
      }),
      item("Mark overdue", "POST", "/monthly-dues/mark-overdue", {
        body: { month: "2026-10" },
      }),
    ]),
    folder("Daily meals", null, [
      item("Fetch meals", "POST", "/daily-meals/fetch", {
        description: "meal_date defaults to today. A member only sees their own row.",
        body: { meal_date: "2026-10-18", user_id: 9 },
      }),
      item("Save meals", "POST", "/daily-meals/save", {
        body: {
          meal_date: "2026-10-18",
          items: [
            {
              user_id: 9,
              lunch_active: true,
              lunch_taken: false,
              dinner_active: true,
              dinner_taken: false,
            },
          ],
        },
      }),
      item("Delete meal", "DELETE", "/daily-meals/{{record_id}}/delete", {}),
    ]),
    folder("Daily menus", null, [
      item("Fetch menus", "POST", "/daily-menus/fetch", {
        description: "Send menu_date for one day, or from_date and to_date. Defaults to the current month.",
        body: { from_date: "2026-10-01", to_date: "2026-10-31" },
      }),
      item("Create menu", "POST", "/daily-menus/create", {
        description: "meal_type is lunch or dinner. Saving the same date and meal updates the dishes.",
        body: {
          menu_date: "2026-10-18",
          meal_type: "lunch",
          dishes: "Rice, dal, vegetable",
        },
      }),
      item("Delete menu", "DELETE", "/daily-menus/{{record_id}}/delete", {}),
    ]),
    folder("Market purchases", null, [
      item("Fetch purchases", "POST", "/market-purchases/fetch", {
        body: { from_date: "2026-10-01", to_date: "2026-10-31" },
      }),
      item("Create purchase", "POST", "/market-purchases/create", {
        description: "create_expense also writes an expense row when true.",
        body: {
          purchase_date: "2026-10-18",
          item_name: "Rice",
          quantity: 5,
          unit: "kg",
          amount: 250,
          note: "Morning market",
          create_expense: true,
        },
      }),
      item("Delete purchase", "DELETE", "/market-purchases/{{record_id}}/delete", {}),
    ]),
    folder("Market duties", null, [
      item("Fetch duties", "POST", "/market-duties/fetch", {
        description: "status: assigned, done, missed, swapped. A member only sees their own duties.",
        body: { from_date: "2026-10-01", to_date: "2026-10-31", status: "assigned" },
      }),
      item("Assign duty", "POST", "/market-duties/create", {
        body: { user_id: 9, duty_date: "2026-10-20", note: "Morning market" },
      }),
      item("Update duty status", "POST", "/market-duties/status", {
        body: { duty_id: 1, status: "done", note: "Bought rice and dal" },
      }),
      item("Delete duty", "DELETE", "/market-duties/{{record_id}}/delete", {}),
    ]),
    folder("Guest meals", null, [
      item("Fetch guest meals", "POST", "/guest-meals/fetch", {
        description: "page is a query param. A member only sees meals booked against them.",
        body: { meal_date: "2026-10-18", member_id: 9 },
        query: [{ key: "page", value: "1" }],
      }),
      item("Create guest meal", "POST", "/guest-meals/create", {
        description: "guest_type: personal or guest. dish_type: chicken, fish, egg, veg. Price comes from the mess guest prices.",
        body: {
          member_id: 9,
          guest_type: "guest",
          dish_type: "chicken",
          quantity: 1,
          meal_date: "2026-10-18",
          meal_type: "lunch",
          guest_name: "Ravi's friend",
          note: "",
        },
      }),
      item("Delete guest meal", "DELETE", "/guest-meals/{{record_id}}/delete", {}),
    ]),
    folder("Constant expenses", "Create and delete are president only.", [
      item("Fetch constant expenses", "POST", "/constant-expenses/fetch", { body: {} }),
      item("Create constant expense", "POST", "/constant-expenses/create", {
        body: { name: "Cook salary", amount: 8000, fund_type: "manager_fund", is_active: true },
      }),
      item("Delete constant expense", "DELETE", "/constant-expenses/{{record_id}}/delete", {}),
    ]),
    folder("Expenses", null, [
      item("Fetch expenses", "POST", "/expenses/fetch", {
        description: "category: market, constant, feast, tour, other. page is a query param.",
        body: { fund_type: "manager_fund", category: "market", from_date: "2026-10-01", to_date: "2026-10-31" },
        query: [{ key: "page", value: "1" }],
      }),
      item("Create expense", "POST", "/expenses/create", {
        body: {
          fund_type: "manager_fund",
          category: "market",
          title: "Morning market",
          amount: 250,
          spent_on: "2026-10-18",
          note: "Rice",
        },
      }),
      item("Delete expense", "DELETE", "/expenses/{{record_id}}/delete", {}),
    ]),
    folder("Mess rules", "Create and delete are president only.", [
      item("Fetch rules", "POST", "/mess-rules/fetch", { body: {} }),
      item("Create rule", "POST", "/mess-rules/create", {
        body: {
          title: "Late meal notice",
          description: "Tell the manager before 10am if you will miss lunch.",
          fine_amount: 50,
          is_active: true,
        },
      }),
      item("Delete rule", "DELETE", "/mess-rules/{{record_id}}/delete", {}),
    ]),
    folder("Fines", "Waive and delete are president. A member can pay their own fine.", [
      item("Fetch fines", "POST", "/fines/fetch", {
        description: "status: pending, paid, waived.",
        body: { status: "pending", user_id: 9 },
      }),
      item("Create fine", "POST", "/fines/create", {
        body: {
          user_id: 9,
          mess_rule_id: 1,
          reason: "Missed meal notice",
          amount: 50,
          fined_on: "2026-10-18",
        },
      }),
      item("Pay fine", "POST", "/fines/pay", { body: { fine_id: 1 } }),
      item("Waive fine", "POST", "/fines/waive", { body: { fine_id: 1 } }),
      item("Delete fine", "DELETE", "/fines/{{record_id}}/delete", {}),
    ]),
  ],
};

const environment = {
  name: "BhuriBhoj local",
  values: [
    { key: "base_url", value: "http://127.0.0.1:8000/api", type: "default", enabled: true },
    { key: "token", value: "", type: "secret", enabled: true },
  ],
  _postman_variable_scope: "environment",
};

writeFileSync(
  new URL("./BhuriBhoj.postman_collection.json", import.meta.url),
  JSON.stringify(collection, null, 2),
);
writeFileSync(
  new URL("./BhuriBhoj.local.postman_environment.json", import.meta.url),
  JSON.stringify(environment, null, 2),
);
