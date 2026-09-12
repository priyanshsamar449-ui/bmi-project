# Study Guide — BMI Calculator

This is a concept-by-concept walkthrough of the whole project, written so you
can explain any part of it in a viva without memorising code you don't
understand. Each section says *what* the code does, *why* it's done that
way, and ends with questions an examiner might ask.

---

## 1. Overall architecture

Plain procedural PHP (no classes, no framework) talking to a MySQL/MariaDB
database through `mysqli`. No JavaScript framework either — just one small
`script.js` file for things that genuinely need to happen instantly in the
browser (live BMI preview, showing/hiding the height fields).

Files, grouped by job:

- **Connection**: `db_config.php`
- **Schema**: `schema.sql`
- **Auth**: `register.php`, `login.php`, `logout.php`
- **Core app**: `index.php` (form + results + history), `calculate.php`
  (does the work, never shows HTML, just redirects back)
- **Extra actions**: `clear_history.php`, `delete_record.php`
- **Front end**: `style.css`, `script.js`

**Q: Why is `calculate.php` separate from `index.php` instead of one file
doing everything?**
A: It follows the Post/Redirect/Get pattern (see §5). `calculate.php` only
ever receives a POST, does its work, and redirects. It never itself
outputs HTML. `index.php` only ever receives a GET and renders. Keeping
"do the work" and "show the page" in separate files makes each one easier
to reason about and test on its own.

---

## 2. Sessions and authentication

`session_start()` is the first line of every page that needs to know who's
logged in. It looks at the `PHPSESSID` cookie the browser sends and loads
that user's `$_SESSION` array from storage on the server.

- On successful login/register: `$_SESSION['user_id']` and
  `$_SESSION['username']` are set.
- On every protected page: `if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }`
  This is the access-control check. Without it, anyone could open
  `index.php` directly and see... nothing useful actually, because every
  query is also filtered by `user_id` — but the redirect is still the
  first line of defence and gives a clean login prompt instead of a
  broken page.
- `logout.php` calls `session_destroy()`, which wipes the session data on
  the server so the cookie the browser still has becomes useless.

**Q: If someone steals a cookie, can they log in as that user?**
A: Yes — sessions are a shared-secret model. This project doesn't defend
against cookie theft (HTTPS in production and the `httponly`/`secure`
cookie flags help, but that's beyond scope here).

---

## 3. Passwords: hashing, never storing plain text

`register.php` never stores the password itself, only
`password_hash($password, PASSWORD_DEFAULT)` — a one-way scrambled version.
`login.php` never "unscrambles" it either; it uses
`password_verify($password, $user['password'])`, which re-hashes the typed
password with the same algorithm/salt stored inside the hash and compares
the results.

**Q: Why not just compare `$password == $storedPassword`?**
A: If the `users` table ever leaked (backup, SQL injection, whatever),
plain-text passwords would hand over every account immediately — and since
people reuse passwords, probably accounts on other sites too. A hash can't
be reversed back into the original password.

---

## 4. Prepared statements (stopping SQL injection)

Every single query in this project uses `mysqli_prepare` +
`mysqli_stmt_bind_param`, never string concatenation like
`"...WHERE username = '$username'"`.

```php
$stmt = mysqli_prepare($conn, "SELECT id, username, password FROM users WHERE username = ?");
mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
```

The `?` is a placeholder. `bind_param`'s first argument is a type string —
`"s"` for string, `"i"` for integer, `"d"` for double/float. The database
driver sends the query *and* the values separately, so whatever a user
types is always treated as data, never as part of the SQL command itself.
That's what stops someone typing `' OR '1'='1` into a login box and
bypassing the password check.

**Q: What would happen if we used string concatenation instead?**
A: A malicious username like `' OR '1'='1' --` could change the meaning of
the SQL query entirely — this is SQL injection, one of the most common
real-world web vulnerabilities.

---

## 5. Post/Redirect/Get, and passing data through the URL

`calculate.php` finishes with:

```php
header("Location: index.php?bmi=$bmi&category=$category&height=$height_cm");
exit();
```

This is the Post/Redirect/Get pattern: the browser POSTs the form, the
server processes it and responds with a redirect (HTTP 302), and the
browser then does a fresh GET to `index.php`. Two benefits:

1. Refreshing the results page never resubmits the form (no "confirm form
   resubmission" browser prompt, no accidentally double-saving a record).
2. `index.php` can read the just-calculated `bmi`, `category`, and
   `height` straight from `$_GET`, without needing a second database query.

`index.php` reads them defensively:

```php
$result_bmi = isset($_GET['bmi']) ? floatval($_GET['bmi']) : null;
```

`floatval()` matters here — anything in the URL is a string as far as PHP
is concerned, and casting it up front means all the maths later
(`$result_bmi - $gauge_min`, etc.) behaves predictably.

**Q: Why pass `height` through the URL instead of just re-reading it from
the database?**
A: We could, but we already have it right after `calculate.php` computed
it — passing it along avoids a query we don't need.

---

## 6. The BMI formula and category cutoffs

```php
$height_m = $height_cm / 100;
$bmi = $weight_kg / ($height_m * $height_m);
```

Standard formula: `BMI = weight(kg) / height(m)²`. Age and gender are
**not** part of this formula — they're stored purely so the diet
suggestion text (§10) can be picked, and so history is more informative.

Category cutoffs used in this project are the standard **WHO/global
cutoffs** — the same ones used by most well-known BMI calculators:

| Category    | BMI       |
|-------------|-----------|
| Underweight | < 18.5    |
| Normal      | 18.5–24.9 |
| Overweight  | 25–29.9   |
| Obese       | 30+       |

(There's also a separate set of *Asian-specific* WHO cutoffs — 18.5 /
23 / 27.5 — used in some clinical contexts because research found health
risk shows up at a somewhat lower BMI in South and East Asian
populations. This project uses the standard global cutoffs throughout,
so results line up with common calculators like calculator.net.)

**Q: Why doesn't BMI use age or gender at all?**
A: BMI is deliberately simple — just a ratio of weight to height² — which
is exactly why it's widely used but also why it's an imperfect measure
(it can't tell muscle from fat, for example). More refined measures do
factor in age/gender/body composition, but that's outside BMI's scope.

---

## 7. Height input: cm or feet+inches

The form has a `height_unit` hidden field plus two toggle buttons (`cm`
and `ft / in`). JavaScript (`setHeightUnit()` in `script.js`) does three
things when you switch units:

1. Shows the right input fields, hides the other set.
2. Toggles the HTML `required` attribute on the fields, so the browser
   doesn't block submission for the hidden set's empty inputs.
3. Updates the hidden `height_unit` field's value.

On the server, `calculate.php` checks that value and converts:

```php
if ($height_unit === 'ft') {
    $height_cm = ($feet * 30.48) + ($inches * 2.54);
} else {
    $height_cm = floatval($_POST['height']);
}
```

`1 foot = 30.48 cm` and `1 inch = 2.54 cm` are fixed conversion constants.
After this point, the rest of the code (BMI formula, gauge, healthy
range) only ever deals with `$height_cm` — it doesn't know or care which
unit the user originally typed in.

**Q: Why convert on the server instead of just converting in JavaScript
and always submitting cm?**
A: We *do* convert on the server (that's the authoritative calculation).
JavaScript also does its own quick conversion for the **live preview**
(§9), but that's just a convenience — if JavaScript were disabled, the
form would still submit correctly and `calculate.php` would still do the
real conversion.

---

## 8. The semicircular gauge (SVG + rotation)

This is the newest and most math-heavy piece, so it gets its own detailed
section.

### 8.1 Why SVG

SVG (Scalable Vector Graphics) describes shapes with coordinates and math
instead of pixels, so it stays crisp at any size and we can draw curves
(arcs) precisely with code.

### 8.2 The four coloured bands

Each band is drawn as an **arc** — a curved *stroke* (outline), not a
filled pie slice. An SVG arc command looks like:

```
M startX startY A radius radius 0 largeArcFlag sweepFlag endX endY
```

- `M startX startY` — move the "pen" to the starting point without drawing.
- `A radius radius ...` — draw an arc of that radius to the end point.
- `largeArcFlag` — 0 here, because none of our four bands sweep more than
  180°.
- `sweepFlag` — 1, meaning "draw the arc going clockwise."
- `endX endY` — where the arc ends.

The actual start/end coordinates for each band come from converting a BMI
value into a point on a circle (§8.3) — they're written directly into the
SVG as fixed numbers because the cutoffs (18.5, 25, 30) don't change from
one calculation to the next, only the *needle* does.

### 8.3 Turning a BMI value into a point on the circle (polar coordinates)

The gauge represents BMI values from **12 to 40** as a semicircle (180°),
with a pivot point at the centre-bottom, `(150, 150)` in our
`viewBox="0 0 300 170"` coordinate space, and radius `120`.

Step 1 — turn the BMI into a **percentage** of that range:

```
percent = (bmi - 12) / (40 - 12)      // clamped between 0 and 1
```

Step 2 — turn that percentage into an **angle**, measured the normal
mathematical way (0° = pointing right, 90° = pointing straight up, 180° =
pointing left):

```
angle = 180 * (1 - percent)
```

At `percent = 0` (BMI 12), `angle = 180°` → pointing left.
At `percent = 0.5` (BMI 26), `angle = 90°` → pointing straight up.
At `percent = 1` (BMI 40), `angle = 0°` → pointing right.

Step 3 — turn the angle into an actual `(x, y)` point using the standard
parametric circle equations:

```
x = centreX + radius * cos(angle)
y = centreY - radius * sin(angle)      // MINUS, because SVG's y-axis
                                        // points down, not up
```

That last minus sign trips people up the most: in normal maths, y
increases upward. In SVG (and most computer graphics), y increases
**downward** — `(0,0)` is the top-left corner of the image. So to make a
larger angle move the point *upward* on screen, we have to subtract.

### 8.4 Rotating the needle

The needle is drawn once, in its "resting" position pointing **left**:

```html
<line x1="150" y1="150" x2="70" y2="150" class="needle"/>
```

Then it's rotated with the SVG `transform="rotate(angle, cx, cy)"`
attribute — a built-in SVG feature that spins an element by `angle`
degrees **clockwise**, around the pivot point `(cx, cy)`.

The angle to rotate by turns out to be the *same* `180 * percent` from
step 2 above, but reasoned about differently: the needle starts pointing
left (equivalent to 180° in our maths-angle system), and rotating it
clockwise by `180 * percent` degrees sweeps it from left, up over the
top, and round to the right as `percent` goes from 0 to 1 — landing it on
exactly the same point the bands' arc math describes.

```php
$percent = ($result_bmi - 12) / (40 - 12);
$percent = max(0, min(1, $percent));   // clamp, so an extreme BMI doesn't
                                        // spin the needle off past 40 or before 12
$needle_rotation = 180 * $percent;
```

```html
<line ... transform="rotate(<?php echo $needle_rotation; ?> 150 150)"/>
```

**Q: Why use the SVG `transform` attribute instead of CSS
`transform: rotate()`?**
A: Both exist. The SVG attribute form (`rotate(angle, cx, cy)`) lets you
specify the pivot point directly in the same coordinate system as the
shape, which is simpler here than also setting a separate CSS
`transform-origin`. Either approach is valid; this project uses the SVG
attribute because the pivot point is exactly the same `(150, 150)` used
everywhere else in the gauge math.

**Q: What does "clamped" mean and why do it?**
A: `max(0, min(1, $percent))` forces the value to stay between 0 and 1. If
someone had a BMI of, say, 55, without clamping the needle would try to
rotate past 180° and end up pointing somewhere nonsensical (or even
backwards past the left edge). Clamping just pins it at the nearest end
of the gauge instead.

---

## 9. Live preview (JavaScript)

`script.js` listens for `input` events on the height/weight fields and
recalculates BMI instantly in the browser — before the form is even
submitted. This is purely a UX nicety; it does **not** save anything or
decide the "real" result. The real calculation always happens again on
the server in `calculate.php`, because client-side JavaScript can be
disabled or tampered with and should never be the only check.

The height-unit toggle also lives here: `setHeightUnit('cm')` or
`setHeightUnit('ft')` show/hide the right fields, toggle their `required`
attributes, and re-run the preview using whichever unit is active.

**Q: If I turn off JavaScript in my browser, does the site still work?**
A: Yes, for the actual calculation — you'd just lose the live preview and
the cm/ft toggle would default to showing the cm field only (since that's
the one marked `required` in the HTML by default).

---

## 10. Diet suggestions and healthy weight range

Both are simple, no external API involved.

**Diet suggestions** are a plain PHP associative array keyed by category:

```php
$diet_tips = array(
    "Underweight" => "...",
    "Normal"      => "...",
    ...
);
```

and looked up with `$diet_tips[$result_category]` — an array lookup, not
a database query, since the text never changes.

**Healthy weight range** uses the *same* cutoffs as categorisation,
rearranging the BMI formula to solve for weight instead of BMI:

```
BMI = weight / height_m²   →   weight = BMI * height_m²
```

```php
$healthy_min = round(18.5 * $h_m * $h_m, 1);   // BMI 18.5 = bottom of Normal
$healthy_max = round(24.9 * $h_m * $h_m, 1);   // BMI 24.9 = top of Normal
```

**Q: Why do the healthy-range numbers and the category cutoffs need to
match?**
A: The "Normal" category tops out at BMI 24.9, so the healthy weight
range has to use that same number — if it used a different cutoff, the
two numbers shown on screen would contradict each other (e.g. a weight
inside the "healthy range" that the category label still calls
Overweight).

---

## 11. History: per-user isolation, delete-one-row, and trend arrow

### 11.1 Per-user isolation

Every query touching `bmi_records` filters by `WHERE user_id = ?`, using
the id from `$_SESSION['user_id']` (never trusting anything from the
form/URL for *whose* data to show). This is what stops one logged-in user
from ever seeing another user's history, even though it's all in the same
table.

### 11.2 Deleting a single row

`delete_record.php` is short but the important line is:

```php
$stmt = mysqli_prepare($conn, "DELETE FROM bmi_records WHERE id = ? AND user_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $record_id, $user_id);
```

Both conditions matter. `id = ?` picks the specific row. `AND user_id = ?`
makes sure that even if someone edited the URL to try deleting a row that
belongs to a different account (`delete_record.php?id=17`), the query
simply matches zero rows and deletes nothing — it never even needs an
`if` statement checking ownership separately, the SQL condition does it.

### 11.3 Trend arrow

`index.php` fetches all of a user's history rows (ordered newest first)
into a plain PHP array, then just looks at the first two:

```php
if (count($history_rows) >= 2) {
    $latest = $history_rows[0]['bmi'];
    $previous = $history_rows[1]['bmi'];
    $trend = $latest > $previous ? 'up' : ($latest < $previous ? 'down' : 'same');
}
```

No extra database query needed — the same array already being fetched for
the history table underneath is reused for this.

**Q: Why fetch all rows into an array (`mysqli_fetch_all`) instead of
looping through the mysqli result once with `while`?**
A: A `mysqli` result can normally only be looped through once, start to
finish. We need to look at the two most recent rows (for the trend arrow)
*and* separately loop over every row (to build the table) — pulling
everything into a plain PHP array up front lets us do both without
running the query twice.

---

## 12. Removing the animated background

The original design animated the page background through a gradient
using a CSS `@keyframes` rule and `animation: ambientShift 20s ...`. That's
now gone — replaced with a single flat colour:

```css
body {
  background: var(--canvas);
}
```

Simpler, matches the flat/functional look of the rest of the interface,
and one less thing to explain if asked "why does the background move?"
in a viva.

---

## Quick viva cheat-sheet

- **What stops SQL injection here?** Prepared statements everywhere (§4).
- **What stops one user seeing another's data?** Every query filters by
  `user_id` from the session, never from user input (§11.1).
- **Where are passwords stored?** Never in plain text —
  `password_hash()` / `password_verify()` (§3).
- **Why redirect after calculating instead of just printing the result?**
  Post/Redirect/Get, avoids duplicate form submissions on refresh (§5).
- **How does the needle know which way to point?** BMI → percentage of
  the 12–40 range → angle → SVG rotation, all explained with the actual
  formulas in §8.
- **What BMI cutoffs does this app use?** The standard WHO/global ones —
  18.5 / 25 / 30 — matching most common BMI calculators (§6).
