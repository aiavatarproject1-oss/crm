import asyncio
import logging
import os
import time
from dataclasses import dataclass, field
from typing import Optional

import httpx
from dotenv import load_dotenv
from pyrogram import Client, filters, idle
from pyrogram.enums import ChatAction
from pyrogram.types import Message

load_dotenv()

# ----------------------------- Config -----------------------------
API_ID = int(os.getenv("TELEGRAM_API_ID", "33745314"))
API_HASH = os.getenv("TELEGRAM_API_HASH", "e97997b623db97a5c87c8e008accc3c1")

CRM_BASE_URL = os.getenv("CRM_BASE_URL", "https://resale-displace-banker.ngrok-free.dev/api").rstrip("/")
TENANT_ID = os.getenv("TENANT_ID", "tenant-demo")
CHARACTER_ID = os.getenv("CHARACTER_ID", "character-estelle")
PLATFORM = "telegram"
API_KEY = os.getenv("API_KEY", "")

DEBOUNCE_SECONDS = 2.0
POLL_INTERVAL = 1.5
POLL_TIMEOUT = 120
POLL_TIMEOUT_MEDIA = 300
MEDIA_DC_SETTLE_SECONDS = 1.2

# NEVER send canned replies to users (especially never Persian).
# Failures → silent (+ support notify for media/handoff).
DEBUG = True
# ------------------------------------------------------------------

logging.basicConfig(level=logging.INFO, format="%(asctime)s | %(levelname)s | %(message)s")
log = logging.getLogger("crm-bot")

# One Telegram MTProto session. Never overlap media-DC download with typing/send.
app = Client(
    "crm_userbot",
    api_id=API_ID,
    api_hash=API_HASH,
    workers=1,  # one update worker — prevents photo+caption races
)

_http_client: Optional[httpx.AsyncClient] = None
tg_lock = asyncio.Lock()  # serialize ALL Telegram API calls


async def _http() -> httpx.AsyncClient:
    global _http_client
    if _http_client is None:
        _http_client = httpx.AsyncClient(timeout=httpx.Timeout(90.0))
    return _http_client


def _headers() -> dict:
    h = {
        "Accept": "application/json",
        "Content-Type": "application/json",
        "ngrok-skip-browser-warning": "true",
        "X-Correlation-ID": f"tg-bot-{int(time.time())}",
    }
    if API_KEY:
        h["X-API-Key"] = API_KEY
    return h


def _json(r: httpx.Response):
    try:
        return r.json()
    except Exception:
        log.error("non-JSON response from %s (status %s): %s", r.request.url, r.status_code, r.text[:800])
        raise


async def _post(url: str, json: dict) -> dict:
    h = await _http()
    r = await h.post(url, json=json, headers=_headers())
    if r.status_code >= 400:
        log.error("POST %s -> %s: %s", url, r.status_code, r.text[:800])
        r.raise_for_status()
    return _json(r)


async def _get(url: str, params: Optional[dict] = None) -> dict:
    h = await _http()
    r = await h.get(url, params=params, headers=_headers())
    if r.status_code >= 400:
        log.error("GET %s -> %s: %s", url, r.status_code, r.text[:800])
        r.raise_for_status()
    return _json(r)


@dataclass
class Buffer:
    messages: list = field(default_factory=list)
    task: Optional[asyncio.Task] = None
    username: Optional[str] = None
    external_user_id: Optional[str] = None


buffers: dict[int, Buffer] = {}
last_sent: dict[str, str] = {}
photo_inflight: dict[int, int] = {}
chat_busy: dict[int, asyncio.Lock] = {}


def _chat_lock(chat_id: int) -> asyncio.Lock:
    lock = chat_busy.get(chat_id)
    if lock is None:
        lock = asyncio.Lock()
        chat_busy[chat_id] = lock
    return lock


def _get_buffer(chat_id: int) -> Buffer:
    buf = buffers.get(chat_id)
    if buf is None:
        buf = Buffer()
        buffers[chat_id] = buf
    return buf


def _reschedule(client: Client, chat_id: int, buf: Buffer):
    if buf.task and not buf.task.done():
        buf.task.cancel()
    buf.task = asyncio.create_task(schedule_flush(client, chat_id))


def _set_identity(buf: Buffer, message: Message):
    user = message.from_user
    buf.username = (user.username or user.first_name) if user else None
    buf.external_user_id = f"tg-{message.chat.id}"


async def schedule_flush(client: Client, chat_id: int):
    try:
        await asyncio.sleep(DEBOUNCE_SECONDS)
        deadline = time.monotonic() + 60
        while photo_inflight.get(chat_id, 0) > 0 and time.monotonic() < deadline:
            await asyncio.sleep(0.25)
    except asyncio.CancelledError:
        return

    buf = buffers.pop(chat_id, None)
    if not buf or not buf.messages:
        return

    async with _chat_lock(chat_id):
        await process_turn(client, chat_id, buf)


async def upload_image_bytes(content: bytes, name: str) -> str:
    """Upload to CRM over HTTP — does NOT touch Telegram session."""
    h = await _http()
    headers = {
        "Accept": "application/json",
        "ngrok-skip-browser-warning": "true",
        "X-Correlation-ID": f"tg-media-{int(time.time())}",
    }
    if API_KEY:
        headers["X-API-Key"] = API_KEY

    files = {"file": (name or "photo.jpg", content, "image/jpeg")}
    data = {
        "tenant_id": TENANT_ID,
        "character_id": CHARACTER_ID,
        "influencer_id": CHARACTER_ID,
    }
    r = await h.post(f"{CRM_BASE_URL}/v1/inbound/media", headers=headers, files=files, data=data)
    if r.status_code >= 400:
        log.error("CRM media upload failed %s: %s", r.status_code, r.text[:500])
        r.raise_for_status()

    body = _json(r)
    payload = body.get("data", body) if isinstance(body, dict) else {}
    url = (payload.get("url") or "").strip()
    if url.startswith("http://") and "ngrok" in url:
        url = "https://" + url[len("http://"):]
    if not url.startswith("http"):
        raise RuntimeError(f"CRM media upload missing url: {body}")
    log.info("CRM media uploaded → %s", url)
    return url


# ---------- handlers ----------

@app.on_message(filters.incoming & filters.private & filters.text & ~filters.media)
async def on_text(client: Client, message: Message):
    if not message.text or message.text.startswith("/"):
        return
    chat_id = message.chat.id
    async with tg_lock:
        try:
            await client.read_chat_history(chat_id)
        except Exception:
            log.exception("read_chat_history failed")
    buf = _get_buffer(chat_id)
    _set_identity(buf, message)
    buf.messages.append({
        "external_message_id": f"tg-{chat_id}-{message.id}",
        "text": message.text,
    })
    _reschedule(client, chat_id, buf)


@app.on_message(filters.incoming & filters.private & filters.photo)
async def on_photo(client: Client, message: Message):
    await handle_image(client, message)


@app.on_message(filters.incoming & filters.private & filters.document)
async def on_document(client: Client, message: Message):
    mime = (message.document.mime_type or "") if message.document else ""
    if mime.startswith("image/"):
        await handle_image(client, message)
    else:
        async with tg_lock:
            try:
                await client.read_chat_history(message.chat.id)
            except Exception:
                pass


@app.on_message(
    filters.incoming & filters.private
    & (filters.video | filters.animation | filters.audio
       | filters.voice | filters.sticker | filters.video_note)
)
async def on_other_media(client: Client, message: Message):
    async with tg_lock:
        try:
            await client.read_chat_history(message.chat.id)
        except Exception:
            pass


async def handle_image(client: Client, message: Message):
    """
    Critical: download from Telegram media DC MUST NOT overlap with typing/send.
    Logs like "Connected DC4 / Disconnected" during download are normal MTProto media hops.
    """
    chat_id = message.chat.id
    photo_inflight[chat_id] = photo_inflight.get(chat_id, 0) + 1
    url = None
    try:
        async with tg_lock:
            try:
                await client.read_chat_history(chat_id)
            except Exception:
                log.exception("read_chat_history failed")

        content = b""
        name = "photo.jpg"
        async with tg_lock:
            try:
                raw = await message.download(in_memory=True)
                content = raw.getvalue() if hasattr(raw, "getvalue") else (raw or b"")
                if message.document and message.document.file_name:
                    name = message.document.file_name
            except Exception:
                log.exception("telegram photo download failed")
                content = b""

        # Let media-DC session settle before any other Telegram call
        await asyncio.sleep(MEDIA_DC_SETTLE_SECONDS)

        if content:
            try:
                url = await upload_image_bytes(content, name)
            except Exception:
                log.exception("CRM media upload failed")

        mime = "image/jpeg"
        if message.document and message.document.mime_type:
            mime = message.document.mime_type

        item = {
            "external_message_id": f"tg-{chat_id}-{message.id}",
            "content_type": "image",
            "text": message.caption or "[image]",
        }
        if url:
            item["media"] = [{"url": url, "type": "image", "mime_type": mime}]

        buf = _get_buffer(chat_id)
        _set_identity(buf, message)
        buf.messages.append(item)
        _reschedule(client, chat_id, buf)
    finally:
        photo_inflight[chat_id] = max(0, photo_inflight.get(chat_id, 1) - 1)
        if photo_inflight[chat_id] == 0:
            photo_inflight.pop(chat_id, None)


async def process_turn(client: Client, chat_id: int, buf: Buffer):
    has_media = any(m.get("media") or m.get("content_type") == "image" for m in buf.messages)
    typing: Optional[asyncio.Task] = None
    data: dict = {}
    conversation_id: Optional[str] = None

    try:
        # CRM inbound + poll are HTTP — do NOT hold Telegram lock / do NOT type yet
        data = await send_inbound(buf)
        task_id = data.get("task_id")
        conversation_id = data.get("conversation_id")
        message_batch_id = data.get("message_batch_id") or data.get("batch_id")

        if data.get("handoff") or data.get("ai_skipped"):
            log.info("conversation %s already in handoff; staying silent", conversation_id)
            await notify_support(client, conversation_id, data)
            return

        if not task_id or not conversation_id:
            log.error("inbound response missing ids: %s", data)
            if has_media:
                await notify_support(client, conversation_id, data)
            return

        # Typing only AFTER media download is done and inbound is accepted
        typing = asyncio.create_task(keep_typing(client, chat_id))

        timeout = POLL_TIMEOUT_MEDIA if has_media else POLL_TIMEOUT
        task = await poll_task(task_id, timeout)
        merged = {**data, **task}
        if not merged.get("notify"):
            merged["notify"] = data.get("notify") or []
        if not merged.get("panel_url"):
            merged["panel_url"] = data.get("panel_url")
        if not merged.get("message_batch_id"):
            merged["message_batch_id"] = message_batch_id

        status = str(task.get("status") or "").upper()
        if status != "COMPLETED":
            log.warning("task %s ended with %s — silent", task_id, status)
            if has_media:
                await notify_support(client, conversation_id, merged)
            return

        # Photo turns: if vision did not run, never invent a chat reply
        if has_media and not task.get("vision_used"):
            log.info("photo turn without vision — silent + support (%s)", conversation_id)
            await notify_support(client, conversation_id, merged)
            return

        if task.get("silent") or task.get("handoff") or task.get("vision_fail"):
            log.info("silent/handoff task %s", task_id)
            await notify_support(client, conversation_id, merged)
            return

        # ROOT FIX: never take "latest" assistant message — only THIS turn's reply.
        msg = await fetch_reply_for_task(conversation_id, task, message_batch_id)
        if not msg:
            log.warning(
                "no bound assistant reply for conversation=%s batch=%s assistant_message_id=%s",
                conversation_id,
                merged.get("message_batch_id"),
                task.get("assistant_message_id"),
            )
            if has_media:
                await notify_support(client, conversation_id, merged)
            return

        if msg.get("vision_fail") or msg.get("silent") or isinstance(msg.get("handoff"), dict):
            log.info("silent handoff message for %s", conversation_id)
            await notify_support(client, conversation_id, {**merged, **(msg.get("handoff") or {})})
            return

        text = _message_text(msg)
        media = _message_media(msg)
        if text:
            await _safe_send(client, chat_id, text)
        for media_url in media:
            async with tg_lock:
                try:
                    await client.send_photo(chat_id, media_url)
                except Exception:
                    log.exception("send_photo failed: %s", media_url)
        if not text and not media:
            log.warning("empty assistant reply — silent")
    except Exception:
        log.exception("turn failed — silent (no user canned reply)")
        if has_media:
            await notify_support(client, conversation_id, data if isinstance(data, dict) else {})
    finally:
        if typing is not None:
            typing.cancel()
            try:
                await typing
            except asyncio.CancelledError:
                pass


async def _safe_send(client: Client, chat_id: int, text: str):
    async with tg_lock:
        try:
            await client.send_message(chat_id, text)
        except Exception:
            log.exception("send_message failed")


async def notify_support(client: Client, conversation_id: Optional[str], payload: dict):
    handoff = payload.get("handoff") if isinstance(payload.get("handoff"), dict) else {}
    targets = []
    for key in ("notify", "support_telegram_ids"):
        for t in payload.get(key) or handoff.get(key) or []:
            if isinstance(t, str) and t.strip():
                targets.append(t.strip())
    targets = list(dict.fromkeys(targets))
    if not targets:
        log.warning("handoff notify skipped — no support ids (conversation %s)", conversation_id)
        return

    mode = str(payload.get("notify_mode") or handoff.get("notify_mode") or "all").lower()
    if mode == "round_robin" and targets and conversation_id:
        targets = [targets[sum(ord(c) for c in conversation_id) % len(targets)]]

    reason = payload.get("reason") or handoff.get("reason") or "vision_fail"
    panel = payload.get("panel_url") or handoff.get("panel_url") or ""
    lines = [
        "🚨 Handoff",
        f"reason: {reason}",
        f"character: {CHARACTER_ID}",
    ]
    if conversation_id:
        lines.append(f"conversation: {conversation_id}")
    if panel:
        lines.append(f"panel: {panel}")
    text = "\n".join(lines)

    async with tg_lock:
        try:
            me = await client.get_me()
            my_username = (me.username or "").lower() if me else ""
        except Exception:
            my_username = ""

    for target in targets:
        try:
            peer = await _resolve_support_peer(client, target, my_username)
            async with tg_lock:
                await client.send_message(peer, text)
            log.info("handoff notified → %s (peer=%s)", target, peer)
        except Exception:
            log.exception("handoff notify failed → %s", target)


async def _resolve_support_peer(client: Client, target: str, my_username: str):
    raw = target.strip()
    if raw.startswith("https://t.me/"):
        raw = raw.rsplit("/", 1)[-1]
    username = raw.lstrip("@")
    if username.isdigit():
        return int(username)
    if my_username and username.lower() == my_username:
        return "me"
    async with tg_lock:
        try:
            user = await client.get_users(username)
            return user.id
        except Exception:
            log.warning("get_users(%s) failed, sending by username", username)
            return username


async def keep_typing(client: Client, chat_id: int):
    try:
        while True:
            # Never fight media-DC download / send — skip if lock busy
            try:
                await asyncio.wait_for(tg_lock.acquire(), timeout=0.05)
            except asyncio.TimeoutError:
                await asyncio.sleep(5)
                continue
            try:
                try:
                    await client.send_chat_action(chat_id, ChatAction.TYPING)
                except Exception:
                    log.debug("typing skipped (session busy)")
            finally:
                tg_lock.release()
            await asyncio.sleep(5)
    except asyncio.CancelledError:
        pass


async def send_inbound(buf: Buffer) -> dict:
    payload = {
        "tenant_id": TENANT_ID,
        "character_id": CHARACTER_ID,
        "influencer_id": CHARACTER_ID,
        "platform": PLATFORM,
        "external_user_id": buf.external_user_id,
        "username": buf.username,
        "messages": buf.messages,
    }
    log.info("inbound → tenant=%s character=%s msgs=%s", TENANT_ID, CHARACTER_ID, len(buf.messages))
    body = await _post(f"{CRM_BASE_URL}/v1/inbound/messages", payload)
    if DEBUG:
        log.info("inbound raw: %s", body)
    return body.get("data", body)


async def poll_task(task_id: str, timeout: float = POLL_TIMEOUT) -> dict:
    deadline = time.monotonic() + timeout
    last: dict = {}
    while time.monotonic() < deadline:
        d = await _get(f"{CRM_BASE_URL}/v1/ai/tasks/{task_id}")
        last = d.get("data", d)
        status = str(last.get("status", "")).upper()
        if status in ("COMPLETED", "SUCCESS", "DONE"):
            last["status"] = "COMPLETED"
            return last
        if status in ("FAILED", "ERROR"):
            last["status"] = "FAILED"
            return last
        await asyncio.sleep(POLL_INTERVAL)
    last["status"] = "TIMEOUT"
    return last


async def fetch_reply_for_task(
    conversation_id: str,
    task: dict,
    inbound_batch_id: Optional[str] = None,
) -> Optional[dict]:
    """
    Bind reply to THIS AI task only.
    Never fall back to conversation's latest assistant message (that caused
    re-sending old jacket/profile analysis after a new photo).
    """
    params = {
        "tenant_id": TENANT_ID,
        "character_id": CHARACTER_ID,
        "influencer_id": CHARACTER_ID,
        "limit": 100,
    }
    body = await _get(f"{CRM_BASE_URL}/v1/conversations/{conversation_id}/messages", params=params)
    if DEBUG:
        log.info("conversation raw: %s", body)

    items = _extract_list(body)
    assistant = [m for m in items if _is_assistant(m)]

    want_id = str(task.get("assistant_message_id") or "").strip()
    if want_id:
        for m in assistant:
            mid = str(m.get("id") or m.get("message_id") or "")
            if mid == want_id:
                return _mark_sent(conversation_id, m)
        log.warning("assistant_message_id=%s not found in conversation %s", want_id, conversation_id)
        return None

    batch_id = str(
        task.get("message_batch_id")
        or inbound_batch_id
        or ""
    ).strip()
    if batch_id:
        matched = [
            m for m in assistant
            if str(m.get("batch_id") or m.get("message_batch_id") or "") == batch_id
        ]
        if not matched:
            log.warning("no assistant message for batch_id=%s", batch_id)
            return None
        matched.sort(key=_created_at)
        return _mark_sent(conversation_id, matched[-1])

    log.error(
        "task missing assistant_message_id and message_batch_id — refusing to guess latest reply"
    )
    return None


def _mark_sent(conversation_id: str, msg: dict) -> Optional[dict]:
    msg_id = str(msg.get("id") or msg.get("message_id") or "")
    if msg_id and last_sent.get(conversation_id) == msg_id:
        log.info("assistant message %s already sent — skip", msg_id)
        return None
    if msg_id:
        last_sent[conversation_id] = msg_id
    return msg


def _extract_list(body) -> list:
    if isinstance(body, list):
        return body
    data = body.get("data", body)
    if isinstance(data, list):
        return data
    for key in ("messages", "items", "data"):
        v = data.get(key) if isinstance(data, dict) else None
        if isinstance(v, list):
            return v
    return []


def _is_assistant(m: dict) -> bool:
    role = str(m.get("role") or m.get("author") or m.get("sender_type") or "").lower()
    if role in ("assistant", "ai", "bot", "influencer", "system"):
        return True
    if str(m.get("direction") or "").lower() in ("out", "outbound", "outgoing"):
        return True
    if m.get("is_bot") is True or m.get("from_ai") is True:
        return True
    return False


def _message_text(m: dict) -> Optional[str]:
    for key in ("content", "text", "body", "message"):
        v = m.get(key)
        if isinstance(v, str) and v.strip() and v.strip() not in ("[image]", "[media]", "[handoff]"):
            return v
    return None


def _message_media(m: dict) -> list:
    urls = []
    sources = [m.get("media")]
    meta = m.get("metadata")
    if isinstance(meta, dict):
        sources.append(meta.get("media"))
    for src in sources:
        if isinstance(src, list):
            for it in src:
                if isinstance(it, dict) and it.get("url") and it["url"] not in urls:
                    urls.append(it["url"])
    return urls


def _created_at(m: dict):
    return str(m.get("created_at") or m.get("received_at") or m.get("timestamp") or "")


async def main():
    log.info("bot starting → %s / character=%s tenant=%s", CRM_BASE_URL, CHARACTER_ID, TENANT_ID)
    await app.start()
    me = await app.get_me()
    log.info("logged in as @%s (id=%s)", me.username, me.id)
    await idle()
    await app.stop()


if __name__ == "__main__":
    app.run(main())
