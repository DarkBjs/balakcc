import json
import time
import threading
import asyncio
import httpx
from pyrogram import Client, filters
from datetime import timedelta
from FUNC.usersdb_func import *
from FUNC.defs import *
from .gate import *
from .response import *
from TOOLS.check_all_func import *
from TOOLS.getcc_for_mass import *



async def mchkfunc(fullcc , user_id ):
    try:  
        proxies  = await get_proxy_format()
        session  = httpx.AsyncClient(timeout = 30 , proxies = proxies ) 
        result   = await create_cvv_charge(fullcc , session) 
        getresp  = await get_charge_resp(result, user_id, fullcc)
        response = getresp["response"]
        
        await session.aclose()
        status = getresp["status"]


        return f"Card↯ <code>{fullcc}</code>\n<b>Status - {status}</b>\n<b>Result -⤿ {response} ⤾</b>\n\n"

    except:
        import traceback
        await error_log(traceback.format_exc())
        return f"<code>{fullcc}</code>\n<b>Result - Declined ❌</b>\n"
    

@Client.on_message(filters.command("mst", [".", "/"]))
def multi(Client, message):
    t1 = threading.Thread(target=bcall, args=(Client, message))
    t1.start()


def bcall(Client, message):
    loop = asyncio.new_event_loop()
    asyncio.set_event_loop(loop)
    loop.run_until_complete(stripe_mass_auth_cmd(Client, message))
    loop.close()


async def stripe_mass_auth_cmd(Client, message):
    try:
        user_id    = str(message.from_user.id)
        first_name = str(message.from_user.first_name)
        checkall   = await check_all_thing(Client , message)
        if checkall[0] == False:
            return

        role  = checkall[1]
        getcc = await getcc_for_mass(message, role)
        if getcc[0] == False:
            await message.reply_text(getcc[1], message.id)
            return

        ccs  = getcc[1]
        resp = f"""
- 𝐆𝐚𝐭𝐞𝐰𝐚𝐲 -  NONSK 5$ CVV CHARGE

- 𝐂𝐂 𝐀𝐦𝐨𝐮𝐧𝐭 -{len(ccs)}
- 𝐂𝐡𝐞𝐜𝐤𝐞𝐝 - Checking CC For {first_name}

- 𝐒𝐭𝐚𝐭𝐮𝐬 - Processing...⌛️

- 𝐂𝐡𝐞𝐜𝐤𝐞𝐝 - <a href="tg://user?id={message.from_user.id}"> {message.from_user.first_name}</a> ⤿ {role} ⤾
- 𝐎𝐰𝐧𝐞𝐫 - <a href="tg://user?id=6745804180">Toͥnmͣoͫy 〔 Ɠφ 〕</a>
        """
        nov = await message.reply_text(resp, message.id)

        text = f"""
MASS NONSK 5$ CVV CHARGE [/mst]

Number Of CC Check : [{len(ccs)} / 25]

"""
        amt        = 0
        start      = time.perf_counter()
        # proxies    = await get_proxy_format()
        # session    = httpx.AsyncClient(timeout = 100 , proxies = proxies , follow_redirects = True )  
        works      = [mchkfunc(i, user_id ) for i in ccs]
        worker_num = int(json.loads(open("FILES/config.json", "r" , encoding="utf-8").read())["THREADS"])

        while works:
            a = works[:worker_num]
            a = await asyncio.gather(*a)
            for i in a:
                amt += 1
                text += i
                if amt % 5 == 0:
                    try:
                        await Client.edit_message_text(message.chat.id, nov.id, text)
                    except:
                        pass
            await asyncio.sleep(1)
            works = works[worker_num:]
        

        # await session.aclose()
        taken                     = str(timedelta(seconds=time.perf_counter() - start))
        hours , minutes , seconds = map(float, taken.split(":"))
        hour                      = int(hours)
        min                       = int(minutes)
        sec                       = int(seconds)
        results = await getuserinfo(user_id)
        credit = results["credit"]


        text += f"""
- 𝐂𝐡𝐞𝐜𝐤𝐞𝐝 - <a href="tg://user?id={message.from_user.id}"> {message.from_user.first_name}</a> ⤿ {role} ⤾
- 𝐎𝐰𝐧𝐞𝐫 - <a href="tg://user?id=6745804180">Toͥnmͣoͫy 〔 Ɠφ 〕</a>

- 𝗧𝗶𝗺𝗲 -  {hour}.h {min}.m {sec}.s 
"""
        await Client.edit_message_text(message.chat.id, nov.id, text)
        await massdeductcredit(user_id, len(ccs))
        await setantispamtime(user_id)

    except:
        import traceback
        await error_log(traceback.format_exc())
