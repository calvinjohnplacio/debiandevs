pipeline {

    agent any

    options {

        timestamps()

        disableConcurrentBuilds()

        timeout(
            time: 20,
            unit: 'MINUTES'
        )
    }

    environment {

        WEB_DIR = "/var/www/html"

        BACKUP_DIR = "/var/backups/myapp"

        BACKUP_CURRENT = "/var/backups/myapp/current"

        PYTHON = "/opt/selenium-venv/bin/python"

        GITHUB_BRANCH = "main"

        DEPLOYED = "false"

        PHP_CHECK_PASSED = "false"

        SKIP_PIPELINE = "false"
    }


    stages {

        /*
         * ==================================================
         * 1. CHECKOUT
         * ==================================================
         */

        stage('Checkout') {

            steps {

                git(
                    url: 'https://github.com/calvinjohnplacio/debiandevs.git',
                    branch: "${GITHUB_BRANCH}",
                    credentialsId: 'github-pat'
                )

                sh '''
                    set -e

                    echo "========================================"
                    echo "CHECKOUT"
                    echo "========================================"

                    echo ""
                    echo "Commit:"
                    git rev-parse HEAD

                    echo ""
                    echo "Commit message:"
                    git log -1 --pretty=%B

                    echo ""
                    echo "Checkout completed."
                '''
            }
        }


        /*
         * ==================================================
         * 2. PHP SYNTAX CHECK - FIRST REAL TEST
         * ==================================================
         *
         * IMPORTANT:
         *
         * If PHP syntax fails:
         *
         * 1. Jenkins build fails.
         * 2. Deployment does NOT happen.
         * 3. /var/www/html is NOT changed.
         * 4. Jenkins does NOT push anything to GitHub.
         * 5. The bad commit remains in GitHub until you fix it.
         *
         */

        stage('CHECK PHP SYNTAX FIRST') {

            steps {

                sh '''
                    set -e

                    echo "========================================"
                    echo "CHECKING PHP SYNTAX"
                    echo "========================================"

                    PHP_COUNT=$(find "${WORKSPACE}" \
                        -type f \
                        -name "*.php" \
                        -not -path "${WORKSPACE}/vendor/*" \
                        -not -path "${WORKSPACE}@tmp/*" \
                        | wc -l)

                    echo ""
                    echo "PHP files found: ${PHP_COUNT}"

                    if [ "${PHP_COUNT}" -eq 0 ]; then

                        echo ""
                        echo "No PHP files found."

                    else

                        echo ""
                        echo "Running PHP syntax checks..."
                        echo ""

                        find "${WORKSPACE}" \
                            -type f \
                            -name "*.php" \
                            -not -path "${WORKSPACE}/vendor/*" \
                            -not -path "${WORKSPACE}@tmp/*" \
                            -print0 |
                        xargs -0 -n1 php -l

                    fi

                    echo ""
                    echo "========================================"
                    echo "PHP SYNTAX PASSED"
                    echo "========================================"
                '''

                script {
                    env.PHP_CHECK_PASSED = "true"
                }
            }
        }


        /*
         * ==================================================
         * 3. CHECK SELENIUM ENVIRONMENT
         * ==================================================
         */

        stage('Check Selenium Environment') {

            when {

                expression {
                    env.PHP_CHECK_PASSED == "true"
                }
            }

            steps {

                sh '''
                    set -e

                    echo "========================================"
                    echo "CHECKING SELENIUM ENVIRONMENT"
                    echo "========================================"

                    echo ""
                    echo "Python:"
                    ${PYTHON} --version

                    echo ""
                    echo "Selenium:"
                    ${PYTHON} -c \
                        "import selenium; print('Selenium:', selenium.__version__)"

                    echo ""
                    echo "Chromium:"
                    chromium --version

                    echo ""
                    echo "SELENIUM ENVIRONMENT PASSED"
                    echo "========================================"
                '''
            }
        }


        /*
         * ==================================================
         * 4. BACKUP CURRENT WEBSITE
         * ==================================================
         */

        stage('Backup Current Website') {

            when {

                expression {
                    env.PHP_CHECK_PASSED == "true"
                }
            }

            steps {

                sh '''
                    set -e

                    echo "========================================"
                    echo "BACKING UP CURRENT WEBSITE"
                    echo "========================================"

                    sudo mkdir -p "${BACKUP_DIR}"

                    sudo rm -rf "${BACKUP_DIR}/new"

                    sudo mkdir -p "${BACKUP_DIR}/new"

                    sudo rsync -a \
                        "${WEB_DIR}/" \
                        "${BACKUP_DIR}/new/"

                    sudo rm -rf "${BACKUP_CURRENT}"

                    sudo mv \
                        "${BACKUP_DIR}/new" \
                        "${BACKUP_CURRENT}"

                    echo ""
                    echo "========================================"
                    echo "WEBSITE BACKUP COMPLETED"
                    echo "========================================"
                '''
            }
        }


        /*
         * ==================================================
         * 5. DEPLOY
         * ==================================================
         */

        stage('Deploy') {

            when {

                expression {
                    env.PHP_CHECK_PASSED == "true"
                }
            }

            steps {

                script {

                    /*
                     * Mark deployment BEFORE rsync.
                     *
                     * If rsync partially changes the website
                     * and then fails, the post-failure rollback
                     * will restore the backup.
                     */

                    env.DEPLOYED = "true"
                }

                sh '''
                    set -e

                    echo "========================================"
                    echo "DEPLOYING WEBSITE"
                    echo "========================================"

                    sudo rsync -a \
                        --delete \
                        --exclude=".git" \
                        --exclude="Jenkinsfile" \
                        --exclude="tests" \
                        "${WORKSPACE}/" \
                        "${WEB_DIR}/"

                    echo ""
                    echo "DEPLOYMENT COMPLETED"
                '''
            }
        }


        /*
         * ==================================================
         * 6. HTTP TEST
         * ==================================================
         */

        stage('HTTP Test') {

            when {

                expression {
                    env.PHP_CHECK_PASSED == "true"
                }
            }

            steps {

                sh '''
                    set -e

                    echo "========================================"
                    echo "HTTP TEST"
                    echo "========================================"

                    sleep 2

                    HTTP_CODE=$(curl \
                        --output /dev/null \
                        --silent \
                        --show-error \
                        --write-out "%{http_code}" \
                        http://127.0.0.1/)

                    echo ""
                    echo "HTTP status: ${HTTP_CODE}"

                    if [ "${HTTP_CODE}" -lt 200 ] || \
                       [ "${HTTP_CODE}" -ge 400 ]; then

                        echo ""
                        echo "HTTP TEST FAILED."

                        exit 1
                    fi

                    echo ""
                    echo "HTTP TEST PASSED"
                '''
            }
        }


        /*
         * ==================================================
         * 7. SELENIUM TEST
         * ==================================================
         */

        stage('Python Selenium Test') {

            when {

                expression {
                    env.PHP_CHECK_PASSED == "true"
                }
            }

            steps {

                sh '''
                    set -e

                    echo "========================================"
                    echo "SELENIUM TEST"
                    echo "========================================"

                    ${PYTHON} \
                        "${WORKSPACE}/tests/selenium_test.py"

                    echo ""
                    echo "========================================"
                    echo "SELENIUM TEST PASSED"
                    echo "========================================"
                '''
            }
        }


        /*
         * ==================================================
         * 8. SUCCESS
         * ==================================================
         *
         * At this point:
         *
         * PHP       = PASS
         * HTTP      = PASS
         * Selenium  = PASS
         *
         * Therefore the commit is safe to deploy.
         */

        stage('Deployment Verified') {

            when {

                expression {
                    env.PHP_CHECK_PASSED == "true"
                }
            }

            steps {

                sh '''
                    echo "========================================"
                    echo "DEPLOYMENT VERIFIED"
                    echo "========================================"

                    echo ""
                    echo "PHP syntax: PASS"
                    echo "HTTP test:  PASS"
                    echo "Selenium:   PASS"

                    echo ""
                    echo "Current Git commit:"
                    git rev-parse HEAD

                    echo ""
                    echo "Version is valid."
                '''
            }
        }
    }


    /*
     * ======================================================
     * POST ACTIONS
     * ======================================================
     */

    post {


        /*
         * ==================================================
         * SUCCESS
         * ==================================================
         */

        success {

            echo '''
========================================
DEPLOYMENT SUCCESSFUL
========================================

PHP syntax: PASS
HTTP test:  PASS
Selenium:   PASS

The tested GitHub commit is now deployed.
========================================
'''
        }


        /*
         * ==================================================
         * FAILURE
         * ==================================================
         */

        failure {

            script {

                /*
                 * ==================================================
                 * CASE 1:
                 * PHP SYNTAX FAILED
                 * ==================================================
                 *
                 * THIS IS THE IMPORTANT PART.
                 *
                 * Do NOT rollback GitHub.
                 * Do NOT push anything.
                 * Do NOT modify the repository.
                 *
                 * The bad commit stays on GitHub so you can
                 * correct it and push a new commit.
                 *
                 */

                if (env.PHP_CHECK_PASSED != "true") {

                    echo '''
========================================
PHP SYNTAX CHECK FAILED
========================================

DEPLOYMENT CANCELLED.

The website was NOT changed.

GitHub was NOT changed.

Jenkins will NOT create a rollback commit.

Fix the PHP error in GitHub and push
a new commit.

========================================
'''
                }


                /*
                 * ==================================================
                 * CASE 2:
                 * PHP PASSED BUT DEPLOYMENT/TEST FAILED
                 * ==================================================
                 *
                 * In this case deployment may have changed the
                 * website, so restore the previous website backup.
                 *
                 * GitHub is STILL NOT modified.
                 *
                 */

                else {

                    if (env.DEPLOYED == "true") {

                        echo '''
========================================
DEPLOYMENT / TEST FAILED
========================================

PHP syntax passed, but a later stage failed.

Restoring previous website version...
========================================
'''

                        sh '''
                            set +e

                            if [ -d "${BACKUP_CURRENT}" ]; then

                                echo "Restoring website backup..."

                                sudo rsync -a \
                                    --delete \
                                    "${BACKUP_CURRENT}/" \
                                    "${WEB_DIR}/"

                                STATUS=$?

                                if [ "${STATUS}" -eq 0 ]; then

                                    echo ""
                                    echo "========================================"
                                    echo "WEBSITE ROLLBACK SUCCESSFUL"
                                    echo "========================================"

                                else

                                    echo ""
                                    echo "========================================"
                                    echo "WEBSITE ROLLBACK FAILED"
                                    echo "========================================"

                                fi

                            else

                                echo ""
                                echo "NO WEBSITE BACKUP FOUND."

                            fi
                        '''

                    }

                    else {

                        echo '''
========================================
NO DEPLOYMENT WAS PERFORMED
========================================
'''
                    }


                    echo '''
========================================
GITHUB WAS NOT MODIFIED
========================================

Jenkins does not automatically push a
rollback commit to GitHub.

The repository remains at the commit
that triggered this build.

========================================
'''
                }
            }
        }


        /*
         * ==================================================
         * ALWAYS
         * ==================================================
         */

        always {

            echo '''
========================================
JENKINS PIPELINE FINISHED
========================================
'''
        }
    }
}
