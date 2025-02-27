from telegram import Update, ChatPermissions
from telegram.ext import Application, CommandHandler, MessageHandler, filters, CallbackContext


# Dictionary to store user points
user_points = {}

# Mute threshold (adjust as needed)
MUTE_THRESHOLD = 10

# Mute durations (adjust as needed)
MUTE_DURATIONS = {5: 1800, 10: 3600, 20: 86400, 30: 0}  # 30 min, 1 hr, 24 hrs, Permanent

# Admin list (Add your Telegram user ID)
ADMINS = [6918300873]

# Function to handle /start command
def start(update: Update, context: CallbackContext):
    user = update.message.from_user
    update.message.reply_text(
        f"Hello {user.first_name}! 👋\n\n"
        "Welcome to our group! 🚀\n"
        "Here are some rules:\n"
        "1️⃣ Be respectful\n"
        "2️⃣ No spamming\n"
        "3️⃣ Follow the guidelines\n\n"
        "Use /points to check your warning points.\n"
        "Admins can use /warn to issue warnings."
    )

# Function to add points and mute users if needed
def add_violation(update: Update, context: CallbackContext, points: int):
    user = update.message.from_user
    chat_id = update.message.chat_id
    user_id = user.id

    if user_id not in user_points:
        user_points[user_id] = 0

    user_points[user_id] += points
    total_points = user_points[user_id]

    update.message.reply_text(f"{user.first_name} received {points} points. Total: {total_points}")

    # Check if user should be muted
    for threshold, duration in MUTE_DURATIONS.items():
        if total_points >= threshold:
            if duration == 0:
                context.bot.restrict_chat_member(chat_id, user_id, ChatPermissions(can_send_messages=False))
                update.message.reply_text(f"{user.first_name} is permanently muted! 🚫")
            else:
                context.bot.restrict_chat_member(chat_id, user_id, ChatPermissions(can_send_messages=False), until_date=update.message.date + duration)
                update.message.reply_text(f"{user.first_name} is muted for {duration // 60} minutes! ⏳")
            break

# Command: /warn (Admins only)
def warn(update: Update, context: CallbackContext):
    if update.message.from_user.id not in ADMINS:
        return

    if not context.args:
        update.message.reply_text("Usage: /warn @username or reply to a user.")
        return

    user_id = update.message.reply_to_message.from_user.id if update.message.reply_to_message else None
    if user_id:
        add_violation(update, context, points=3)
    else:
        update.message.reply_text("Reply to a user's message to warn them.")

# Command: /points (Check user points)
def check_points(update: Update, context: CallbackContext):
    user_id = update.message.from_user.id
    points = user_points.get(user_id, 0)
    update.message.reply_text(f"You have {points} warning points.")

# Command: /resetpoints (Admins only)
def reset_points(update: Update, context: CallbackContext):
    if update.message.from_user.id not in ADMINS:
        return

    if not update.message.reply_to_message:
        update.message.reply_text("Reply to a user to reset their points.")
        return

    user_id = update.message.reply_to_message.from_user.id
    user_points[user_id] = 0
    update.message.reply_text(f"User's warning points have been reset.")

# Command: /reducepoints (Admins only)
def reduce_points(update: Update, context: CallbackContext):
    if update.message.from_user.id not in ADMINS:
        return

    if not update.message.reply_to_message or not context.args:
        update.message.reply_text("Usage: /reducepoints [number] (reply to user)")
        return

    user_id = update.message.reply_to_message.from_user.id
    reduction = int(context.args[0])

    if user_id in user_points:
        user_points[user_id] = max(0, user_points[user_id] - reduction)
        update.message.reply_text(f"User's warning points reduced by {reduction}. New total: {user_points[user_id]}")

# Command: /mystats (Check self points)
def mystats(update: Update, context: CallbackContext):
    user_id = update.message.from_user.id
    points = user_points.get(user_id, 0)
    update.message.reply_text(f"Your current warning points: {points}")

# Handle banned words
BANNED_WORDS = ["spam", "badword"]

def filter_messages(update: Update, context: CallbackContext):
    text = update.message.text.lower()
    for word in BANNED_WORDS:
        if word in text:
            add_violation(update, context, points=2)
            break

# Main function
def main():
    TOKEN = "7595092616:AAGt729ANHRRdaW1gEw_UpGJ8-ChriBh4p0"

    updater = Updater(TOKEN, use_context=True)
    dp = updater.dispatcher

    # Commands
    dp.add_handler(CommandHandler("start", start))
    dp.add_handler(CommandHandler("warn", warn, pass_args=True))
    dp.add_handler(CommandHandler("points", check_points))
    dp.add_handler(CommandHandler("resetpoints", reset_points))
    dp.add_handler(CommandHandler("reducepoints", reduce_points, pass_args=True))
    dp.add_handler(CommandHandler("mystats", mystats))

    # Message filters
    dp.add_handler(MessageHandler(Filters.text & ~Filters.command, filter_messages))

    # Start bot
    updater.start_polling()
    updater.idle()

if __name__ == "__main__":
    main()

