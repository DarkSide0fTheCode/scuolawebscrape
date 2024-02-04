const express = require("express");
// const puppeteer = require("puppeteer-extra");
const sanitizeHtml = require("sanitize-html");
const validator = require("validator");
const helmet = require("helmet");
const rateLimit = require("express-rate-limit");

const app = express();
const PORT = 3000;

// get env RENDER_EXTERNAL_URL
const render_url = process.env.RENDER_EXTERNAL_URL;
const render_port = process.env.PORT;

if (!render_url) {
  console.log(
    "No RENDER_EXTERNAL_URL found. Please set it as environment variable."
  );
}

if (!render_port) {
  console.log("No PORT found. Please set it as environment variable.");
}

const whitelist = [
  "::1",
  "127.0.0.1",
  "::ffff:127.0.0.1",
  "::ffff:172.17.0.1",
  "82.55.177.110",
  "188.216.202.161",
]; // Replace with your whitelisted IPs

// Serve static files from the public directory
// app.use(express.static('static'));

//Functions

const sleep = (ms) => {
  return new Promise((resolve, reject) => {
    setTimeout(() => {
      resolve(true);
    }, ms);
  });
};

const checkStat = ({ page }) => {
  console.log("STEP DIO");
  return new Promise(async (resolve, reject) => {
    var st = setTimeout(() => {
      console.log("Timeout reached, resolving with code 1");
      resolve({
        code: 1,
      });
    }, 3000);
    try {
      console.log("STEP PORCO");
      await sleep(2400); // temp workaround, wait for what?

      var checkStat = await page.evaluate(() => {
        var stat = 0;
        console.log("STEP 0 - Checkstat Function start");
        if (document.querySelector("html")) {
          console.log("STEP 1 - queryselector HTML");
          var html = document.querySelector("html").innerHTML;
          html = String(html).toLowerCase();
          console.log("STEP 2");
          if (html.indexOf("challenges.cloudflare.com/turnstile") > -1) {
            stat = 1;
          }
        } else {
          stat = 2;
        }
        return stat;
      });
      console.log("checkStat:", checkStat);
      if (checkStat !== 0) {
        try {
          var frame = page.frames()[0];
          console.log("Clicking on iframe");
          await page.click("iframe");
          frame = frame.childFrames()[0];
          if (frame) {
            console.log("Hovering and clicking on checkbox");
            await frame.hover('[type="checkbox"]').catch((err) => {
              console.log("Error hovering:", err);
            });
            await frame.click('[type="checkbox"]').catch((err) => {
              console.log("Error clicking:", err);
            });
          }
        } catch (err) {
          console.log("Error in iframe interaction:", err);
        }
      }
      clearInterval(st);
      resolve({
        code: checkStat,
      });
    } catch (err) {
      console.log("Error in checkStat:", err);
      clearInterval(st);
      resolve({
        code: 1,
      });
    }
  });
};

const send = ({ url = "", proxy = {} }) => {
  return new Promise(async (resolve, reject) => {
    try {
      console.log("Starting send function");
      var { puppeteerRealBrowser } = await import("puppeteer-real-browser");
      var data = {};
      if (proxy && proxy.host && proxy.host.length > 0) {
        data.proxy = proxy;
      }
      puppeteerRealBrowser = await puppeteerRealBrowser(data);
      var browser = puppeteerRealBrowser.browser;
      var page = puppeteerRealBrowser.page;
      try {
        console.log("Navigating to url");
        await page.goto(url, { waitUntil: "domcontentloaded" });

        console.log("Checking stat");
        var stat = await checkStat({
          page: page,
        });

        while (stat.code !== 0) {
          console.log("Sleeping for 500ms");
          await sleep(500);
          console.log("Checking stat again");
          stat = await checkStat({
            page: page,
          });
        }
        console.log("Resolving with success");

        resolve({
          code: 200,
          message: "OK",
          data: {
            browser: browser,
            page: page,
          },
        });
      } catch (err) {
        console.log("Error in inner try block:", err);
        await browser.close();
        resolve({
          code: 501,
          message: err.message,
        });
      }
    } catch (error) {
      console.log("Error in outer try block:", error);
      resolve({
        code: 500,
        message: error.message,
      });
    }
  });
};

// Define rate limit rule
const limiter = rateLimit({
  windowMs: 15 * 60 * 1000, // 15 minutes
  max: 100, // limit each IP to 100 requests per windowMs
});

app.use((req, res, next) => {
  console.log(`Request received from ${req.ip}`);
  if (!whitelist.includes(req.ip)) {
    console.log(`Request from ${req.ip} is not authorized.`);
    return res.status(403).send("Not authorized");
  }
  next();
});

app.use(
  helmet.contentSecurityPolicy({
    directives: {
      defaultSrc: ["'self'"],
      scriptSrc: ["'self'", "'unsafe-inline'"],
      styleSrc: ["'self'", "'unsafe-inline'"],
      imgSrc: ["'self'", "data:"],
      connectSrc: ["'self'"],
      fontSrc: ["'self'"],
      objectSrc: ["'none'"],
      mediaSrc: ["'self'"],
      frameSrc: ["'none'"],
    },
  })
);

app.use("/extract", limiter);

app.get("/extract", async (req, res) => {
  console.log("Request received to extract content.");
  const { target } = req.query;

  if (!target) {
    console.log("URL parameter is missing.");
    return res.status(400).send("URL parameter is required");
  }

  // Validate the URL
  if (!validator.isURL(target)) {
    console.log("Invalid URL received.");
    return res.status(400).send("Invalid URL");
  }

  send({
    url: target,
    // proxy: {
    //     host: '<host>',
    //     port: '<port>',
    //     username: '<username>',
    //     password: '<password>',                                            var LsrUFlweWPK8wJIoAusnstat = 0

    // }
  }).then(async (resp) => {
    // const {browser, page} = resp
    console.log("Action after loaded page");
    console.log(resp);
    // const html = resp.data.page.content();
    // console.log(html);
    // var html = document.querySelector('html').innerHTML;
    // console.log(html);

    // const bodyHandle = await resp.data.page.$('body');

    // const html = await resp.data.page.evaluate(body => body.innerHTML, bodyHandle);
    // const html = await resp.data.page.$eval("body", (el) => el.innerHTML);

    try {
      const firstArticleElement = await resp.data.page.$("article");
      if (!firstArticleElement) {
        throw new Error("No article found on the page");
      }
      const firstArticleHTML = await resp.data.page.evaluate(
        (el) => el.innerHTML,
        firstArticleElement
      );
      console.log(firstArticleHTML);
      // Sanitize the article content
      console.log("Sanitizing article content...");
      const sanitizedArticleContent = sanitizeHtml(firstArticleHTML);

      res.send(sanitizedArticleContent);

    } catch (error) {
      console.log(`Failed to retrieve the first article: ${error}`);
      
      // if (error instanceof puppeteerRealBrowser.errors.TimeoutError) {
      //   console.log("Timeout error while loading the page.");
      //   res.status(500).send("Timeout error while loading the page");
      // } else if (error.message.includes("failed to find element")) {
      //   console.log("Failed to find the article element on the page.");
      //   res.status(404).send("Failed to find the article element on the page");
      // } else {
      //   console.log("An unexpected error occurred.");
      //   res.status(500).send("An unexpected error occurred");
      // }
    }

    // console.log(html);

    await resp.data.browser.close();
  });

  // const StealthPlugin = require("puppeteer-extra-plugin-stealth");
  // puppeteer.use(StealthPlugin());

  // try {
  //   console.log("Launching Puppeteer browser...");
  //   const browser = await puppeteer.launch({
  //     headless: "new",
  //     args: ['--no-sandbox', '--disable-setuid-sandbox']
  //   });
  //   console.log("New page created.");
  //   const page = await browser.newPage();

  //   await page.setExtraHTTPHeaders({
  //     "Accept-Language": "en-US,en;q=0.9,ru;q=0.8",
  //   });
  //   await page.setUserAgent("Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.55 Safari/537.36 Edg/96.0.1054.34")
  //   page.setViewport({ width: 1920, height: 1080});
  //   await page.setJavaScriptEnabled(true);

  //   console.log(`Navigating to ${url}`);
  //   await page.goto(url, { waitUntil: "domcontentloaded" });

  //   console.log("Waaaaait");
  //   await page.waitForTimeout(10000);
  //   console.log("Waaaaait Ended!");

  //   let articleContent;
  //   console.log("Bubududu");
  //   console.log(page);

  //   // Replace this with your specific logic to extract article content
  //   console.log("Extracting article content...");
  //   try {
  //     articleContent = await page.$eval("article", (element) =>
  //       element.innerHTML.trim()
  //     );
  //   } catch (error) {
  //     console.log("Article element not found. Falling back to body element.");
  //     articleContent = await page.$eval("body", (element) =>
  //       element.innerHTML.trim()
  //     );
  //   }

  //   // Sanitize the article content
  //   console.log("Sanitizing article content...");
  //   const sanitizedArticleContent = sanitizeHtml(articleContent);

  //   await browser.close();

  //   // Send the sanitized article content as the response
  //   console.log("Sending extracted content in response.");
  //   res.send(sanitizedArticleContent);
  // } catch (error) {
  //   // Log the error for debugging purposes
  //   console.error(error);

  //   // Send a more specific error message based on the error that occurred
  //   if (error instanceof puppeteer.errors.TimeoutError) {
  //     console.log("Timeout error while loading the page.");
  //     res.status(500).send("Timeout error while loading the page");
  //   } else if (error.message.includes("failed to find element")) {
  //     console.log("Failed to find the article element on the page.");
  //     res.status(404).send("Failed to find the article element on the page");
  //   } else {
  //     console.log("An unexpected error occurred.");
  //     res.status(500).send("An unexpected error occurred");
  //   }
  // }
});

app.listen(PORT, () => {
  console.log(`Env Url Var ${render_url}`);
  console.log(`Env Port Var ${render_port}`);
  console.log(`Server is running at http://localhost:${PORT}`);
});
