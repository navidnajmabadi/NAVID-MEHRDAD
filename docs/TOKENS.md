# Why SIPO used so many tokens, and what was changed

Reported: 6 messages used 222K tokens in Admin (about 37K per message). Another account: 3 messages, 285K.

## Findings (measured on the real prompt)

| Cause | Size | What it did |
|---|---|---|
| Admin numbers counted twice | x2 | A reply's tokens were added to the usage table twice (`rrp_sipo_track_tokens` already adds them, then the engine added them again). Real use was about half of what Admin showed. |
| All 32 tool definitions on every call | about 4,900 tokens | Sent on every model call, even for "ok thanks". |
| Full instructions on every call | about 6,300 tokens | Care routines (625), health basics (480), lab results, setup text and more, for every message. |
| Several calls per message | 2 to 4 calls | A reply that saves something makes a call, reads the result, then answers. Each call carries the full 11,200 tokens above. |
| Hidden "thinking" tokens | thousands per call | The model thinks silently and bills for it unless told not to. Tool calls had it off; final answers and file reading did not. |
| Long history | 24 turns | Resent on every call. |
| Files (PDF, photos) | 20K to 90K each | A multi-page lab PDF is read as text and page images in one call, with thinking on. This is the biggest single cost per upload. |

## Changes

- Admin usage no longer double counts.
- Tool and prompt routing (`lib/sipo/router.php`): only the tool groups and prompt blocks the message needs. Measured per call: 11,200 tokens before, 6,400 to 8,200 now (30 to 43% less). Proactive messages and emergencies still get everything.
- Reasoning set to "low" on every call that has no tools (answers, file reading), remembered per model if unsupported.
- 3 tool rounds instead of 4. History 14 turns instead of 24; older turns are summarised after 36.
- Every reply now logs its real cost (`si_request_log`: prompt, completion, cached, reasoning tokens, calls, tools offered). Admin shows tokens per message; the brain graph flags "token heavy" requests (over 9,000 tokens) so NAKI can work on them.

## Still open (not changed, needs your decision)

- PDFs: if lab PDFs stay the main cost, options are a page limit (for example first 4 pages), or sending photos of single pages. The cost per upload is set by the PDF, not by SIPO's instructions.
- Prompt caching: the stable start of the instructions (about 3,100 tokens) is cached automatically by OpenAI at half price when it is identical between calls. The tool list now changes per message, which reduces that saving a little; the new log records `cached_tokens` so the real effect can be read after a week.
