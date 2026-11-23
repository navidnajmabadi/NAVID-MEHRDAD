# SIPAW for iPhone

A native SwiftUI app (iOS 17+). It talks to the same server as the website: same account, same SIPO memory, same pets.

| Tab | What it is |
|---|---|
| SIPO | Native chat (send text, pick answer buttons, send a photo of lab results) |
| Home, Health, Routines | The website pages shown inside the app, signed in automatically |
| More | Plus (Apple In-App Purchase), Lost pet, Walks, Expenses, Rewards, tour, Settings, Help, Delete account, Sign out |

**Payments.** Plus is bought here with Apple In-App Purchase (StoreKit 2). The website keeps card and Apple Pay (Square). Inside the app the website's payment is hidden and refused (`rrp_is_ios_app()`, the web views send the user agent `SIPAWApp`). Apple requires this for digital subscriptions.

## Open and run
1. Open `ios/SIPAW.xcodeproj` in Xcode.
2. Target SIPAW, Signing & Capabilities: choose your Team (your paid developer account). Add the **In-App Purchase** capability.
3. Debug builds talk to `http://127.0.0.1:8099/` (run `php -S 127.0.0.1:8099` in the project, with a local database as in `CLAUDE.md`). Release builds talk to `https://sipaw.nakilab.com/`. Change `Config.swift`/`API.swift` (`Config`) if needed.
4. Run on the simulator. In the simulator the scheme uses `SIPAW.storekit`, a local test product, so you can try the purchase without Apple.

## What you must do in Apple's websites (nobody else can)
1. **App Store Connect → Agreements, Tax and Banking**: sign the *Paid Applications* agreement and add bank and tax details. Without it, subscriptions cannot be sold.
2. **My Apps → New App**: bundle id `com.nakilab.sipaw` (create the identifier in Certificates, Identifiers and Profiles first), name SIPAW.
3. **Subscriptions**: create a group "SIPAW Plus" and one auto-renewable subscription with product id **`com.nakilab.sipaw.plus.monthly`**, duration 1 month, a price (Apple converts it to every country's currency; pick the CAD 19.99 tier or one above it to cover Apple's 15% to 30%). Add a display name, description and a review screenshot of the Plus screen.
4. **Small Business Program**: apply, so Apple keeps 15% instead of 30%.
5. **App Information → App Store Server Notifications**: set the Production URL (and Sandbox URL) to `https://sipaw.nakilab.com/api/apple/notify.php`, version 2.
6. **Privacy**: fill the nutrition label (account email, health-like pet data, purchases) and give the privacy policy URL `https://sipaw.nakilab.com/legal.php`.
7. **App Review notes**: give a test account (an account of yours with a pet) and say the subscription is bought on the Plus screen (More, SIPAW Plus).
8. **Sandbox testing**: App Store Connect → Users and Access → Sandbox → create a tester. Run a build on a real iPhone, buy Plus with the tester, and check that Plus turns on (Admin → Accounts).

## How a purchase is checked (server)
- `GET api/me.php` gives the app an `apple_account_token`, a UUID only this server can compute for that person. The app passes it to StoreKit as `appAccountToken`.
- After a purchase, restore, renewal or launch, the app sends Apple's signed transaction to `POST api/apple/sync.php`.
- `lib/apple.php` verifies it: the certificate chain must end in Apple Root CA - G3 (pinned by SHA-256), the ES256 signature must match, the bundle id and product id must be ours, and the account token must match this person. Then Plus is extended to Apple's expiry date.
- Apple's own notices (`api/apple/notify.php`, App Store Server Notifications V2) keep renewals, expiry and refunds right even if the app is closed.
- Tested here with a made-up certificate chain: valid purchase accepted; wrong account, wrong bundle, wrong product, an unknown chain, a changed payload and a wrong signing key all refused; renewal extends; refund notice ends Plus; a forged notice returns 400.

## Not in this first version
- **Push notifications** (reminders on the lock screen). The website uses web push, which does not exist inside the app. This needs an Apple push key and a small server job, a good next step.
- Native Health, Routines, Walks and Lost pet screens (they are web pages inside the app for now).
- Sign in with Apple. If a login with Google is ever added to the app, Apple then requires it too.
