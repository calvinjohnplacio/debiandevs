from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options
import sys


URL = "http://127.0.0.1"


def main():

    driver = None

    try:

        print("================================")
        print("STARTING SELENIUM")
        print("================================")

        options = Options()

        options.add_argument("--headless")
        options.add_argument("--no-sandbox")
        options.add_argument("--disable-dev-shm-usage")
        options.add_argument("--window-size=1920,1080")

        driver = webdriver.Chrome(
            options=options
        )

        print("Opening:", URL)

        driver.get(URL)

        print("Page title:", driver.title)

        # Test title

        if driver.title != "My PHP App":

            raise Exception(
                "Wrong page title: " + driver.title
            )

        # Test main heading

        title = driver.find_element(
            By.ID,
            "title"
        )

        if not title.is_displayed():

            raise Exception(
                "Main title is not visible."
            )

        # Test login link

        login = driver.find_element(
            By.ID,
            "login"
        )

        if not login.is_displayed():

            raise Exception(
                "Login link is not visible."
            )

        print("")
        print("================================")
        print("SELENIUM TEST PASSED")
        print("================================")

        return 0


    except Exception as error:

        print("")
        print("================================")
        print("SELENIUM TEST FAILED")
        print("================================")

        print(error)

        return 1


    finally:

        if driver:

            driver.quit()


if __name__ == "__main__":

    sys.exit(main())
