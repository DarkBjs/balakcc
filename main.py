from pyrogram import Client
import json
from flask import Flask

plugins = dict(root="BOT")

with open("FILES/config.json", "r", encoding="utf-8") as f:
    DATA      = json.load(f)
    API_ID    = DATA["API_ID"]
    API_HASH  = DATA["API_HASH"]
    BOT_TOKEN = DATA["BOT_TOKEN"]

bot = Client(
    "MY_BOT", 
    api_id    = API_ID, 
    api_hash  = API_HASH, 
    bot_token = BOT_TOKEN, 
    plugins   = plugins 
)

# Simple Web Server for Health Check
app = Flask(__name__)

@app.route('/')
def home():
    return "Bot is running"

if __name__ == "__main__":
    import threading

    # Start bot in a separate thread
    threading.Thread(target=bot.run, daemon=True).start()
    
    # Start Flask server on port 8000
    app.run(host="0.0.0.0", port=8000)
