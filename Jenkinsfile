pipeline {

    agent any

    options {
        timestamps()
        disableConcurrentBuilds()
        timeout(time: 20, unit: 'MINUTES')
    }

    environment {

        WEB_DIR = "/var/www/html"

        BACKUP_DIR = "/var/backups/myapp"

        BACKUP_CURRENT = "/var/backups/myapp/current"

        GOOD_COMMIT_FILE = "/var/backups/myapp/known_good_commit"

        PYTHON = "/opt/selenium-venv/bin/python"

        GITHUB_BRANCH = "main"

        DEPLOYED = "false"

        SKIP_PIPELINE = "false"

        CURRENT_COMMIT = ""
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
                    branch: 'main',
                    credentialsId: 'github-pat'
                )

                script {

                    env.CURRENT_COMMIT = sh(
                        script: 'git rev-parse HEAD',
                        returnStdout: true
                    ).trim()
                }

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
         * 2. CHECK AUTOMATIC ROLLBACK COMMIT
         * ==================================================
         */

        stage('Check Automatic Rollback') {

            steps {

                script {

                    def message = sh(
                        script: 'git log -1 --pretty=%B',
                        returnStdout: true
                    ).trim()

                    if (message.startsWith('Jenkins rollback:')) {

                        echo '''
========================================
AUTOMATIC ROLLBACK COMMIT DETECTED
========================================

This commit was created by Jenkins.

No deployment will be performed.
No second rollback will be created.
'''

                        env.SKIP_PIPELINE = "true"

                    } else {

                        env.SKIP_PIPELINE = "false"
                    }
                }
            }
        }


        /*
         * ==================================================
         * 3. PHP SYNTAX CHECK
         * ==================================================
         *
         * THIS IS INTENTIONALLY FIRST.
         *
         * If PHP has an error:
         *
         * 1. Deployment does NOT happen.
         * 2. Existing /var/www/html remains untouched.
         * 3. GitHub is rolled back to known-good commit.
         *
         */

        stage('CHECK PHP SYNTAX FIRST') {

            when {
                expression {
                    env.SKIP_PIPELINE != "true"
                }
            }

            steps {

                sh '''
                    set -e

                    echo "========================================"
                    echo "CHECKING ALL PHP FILES"
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
                    echo "PHP SYNTAX CHECK PASSED"
                    echo "========================================"
                '''
            }
        }


        /*
         * ==================================================
         * 4. SELENIUM ENVIRONMENT
         * ==================================================
         */

        stage('Check Selenium Environment') {

            when {
                expression {
                    env.SKIP_PIPELINE != "true"
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
                    echo "Selenium environment OK."
                '''
            }
        }


        /*
         * ==================================================
         * 5. BACKUP CURRENT WEBSITE
         * ==================================================
         */

        stage('Backup Current Website') {

            when {
                expression {
                    env.SKIP_PIPELINE != "true"
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
                    echo "Website backup completed."

                    echo ""
                    echo "Backup location:"
                    echo "${BACKUP_CURRENT}"
                '''
            }
        }


        /*
         * ==================================================
         * 6. DEPLOY
         * ==================================================
         */

        stage('Deploy') {

            when {
                expression {
                    env.SKIP_PIPELINE != "true"
                }
            }

            steps {

                script {

                    /*
                     * Mark deployment before rsync.
                     *
                     * If rsync changes files and then fails,
                     * the post-failure rollback will execute.
                     */

                    env.DEPLOYED = "true"
                }

                sh '''
                    set -e

                    echo "========================================"
                    echo "DEPLOYING TO /var/www/html"
                    echo "========================================"

                    sudo rsync -a \
                        --delete \
                        --exclude=".git" \
                        --exclude="Jenkinsfile" \
                        --exclude="tests" \
                        "${WORKSPACE}/" \
                        "${WEB_DIR}/"

                    echo ""
                    echo "Deployment completed."
                '''
            }
        }


        /*
         * ==================================================
         * 7. HTTP TEST
         * ==================================================
         */

        stage('HTTP Test') {

            when {
                expression {
                    env.SKIP_PIPELINE != "true"
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
                    echo "HTTP TEST PASSED."
                '''
            }
        }


        /*
         * ==================================================
         * 8. SELENIUM TEST
         * ==================================================
         */

        stage('Python Selenium Test') {

            when {
                expression {
                    env.SKIP_PIPELINE != "true"
                }
            }

            steps {

                sh '''
                    set -e

                    echo "========================================"
                    echo "PYTHON SELENIUM TEST"
                    echo "========================================"

                    if [ ! -f "${WORKSPACE}/tests/selenium_test.py" ]; then

                        echo ""
                        echo "ERROR:"
                        echo "tests/selenium_test.py was not found."

                        exit 1
                    fi

                    ${PYTHON} \
                        "${WORKSPACE}/tests/selenium_test.py"

                    echo ""
                    echo "SELENIUM TEST PASSED."
                '''
            }
        }


        /*
         * ==================================================
         * 9. MARK CURRENT VERSION AS KNOWN GOOD
         * ==================================================
         *
         * This is reached ONLY when:
         *
         * PHP       = PASS
         * Selenium  = PASS
         * HTTP      = PASS
         * Deployment = PASS
         *
         */

        stage('Mark Version Known Good') {

            when {
                expression {
                    env.SKIP_PIPELINE != "true"
                }
            }

            steps {

                sh '''
                    set -e

                    echo "========================================"
                    echo "MARKING VERSION AS KNOWN GOOD"
                    echo "========================================"

                    sudo mkdir -p "${BACKUP_DIR}"

                    echo "${CURRENT_COMMIT}" | \
                        sudo tee "${GOOD_COMMIT_FILE}" > /dev/null

                    echo ""
                    echo "Known-good commit:"
                    echo "${CURRENT_COMMIT}"

                    echo ""
                    echo "Known-good version saved."
                '''
            }
        }
    }


    /*
     * ======================================================
     * POST
     * ======================================================
     */

    post {

        /*
         * ==================================================
         * SUCCESS
         * ==================================================
         */

        success {

            script {

                if (env.SKIP_PIPELINE == "true") {

                    echo '''
========================================
AUTOMATIC ROLLBACK COMMIT
========================================

No deployment performed.
Rollback loop prevented.
'''

                } else {

                    echo '''
========================================
DEPLOYMENT SUCCESSFUL
========================================

PHP:       PASS
HTTP:      PASS
SELENIUM:  PASS

This version is now KNOWN GOOD.
'''
                }
            }
        }


        /*
         * ==================================================
         * FAILURE
         * ==================================================
         */

        failure {

            script {

                /*
                 * Never rollback an automatic rollback commit.
                 */

                if (env.SKIP_PIPELINE == "true") {

                    echo '''
========================================
AUTOMATIC ROLLBACK COMMIT
========================================

No second rollback will be performed.
'''


                } else {

                    /*
                     * ======================================
                     * READ KNOWN-GOOD COMMIT
                     * ======================================
                     */

                    def goodCommit = sh(
                        script: '''
                            set +e

                            if [ -f "${GOOD_COMMIT_FILE}" ]; then
                                sudo cat "${GOOD_COMMIT_FILE}"
                            fi
                        ''',
                        returnStdout: true
                    ).trim()


                    /*
                     * ======================================
                     * WEBSITE ROLLBACK
                     * ======================================
                     *
                     * Only needed if deployment started.
                     */

                    if (env.DEPLOYED == "true") {

                        echo '''
========================================
DEPLOYMENT FAILED
========================================

Rolling back /var/www/html...
'''

                        sh '''
                            set +e

                            if [ -d "${BACKUP_CURRENT}" ]; then

                                echo "Restoring previous website..."

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

                    } else {

                        echo '''
========================================
NO DEPLOYMENT WAS PERFORMED
========================================

The website was not changed.

Website rollback is not necessary.
'''
                    }


                    /*
                     * ======================================
                     * GITHUB ROLLBACK
                     * ======================================
                     *
                     * This happens even when PHP syntax
                     * fails BEFORE deployment.
                     */

                    if (goodCommit == "") {

                        echo '''
========================================
NO KNOWN-GOOD COMMIT FOUND
========================================

Jenkins has no previous successful
commit saved.

GitHub rollback cannot be performed.
'''

                    } else if (goodCommit == env.CURRENT_COMMIT) {

                        echo '''
========================================
CURRENT COMMIT IS KNOWN GOOD
========================================

No GitHub rollback required.
'''

                    } else {

                        echo '''
========================================
GITHUB ROLLBACK
========================================
'''

                        echo "Bad commit:"
                        echo "${env.CURRENT_COMMIT}"

                        echo ""
                        echo "Known-good commit:"
                        echo "${goodCommit}"


                        /*
                         * Use environment variables rather than
                         * Groovy interpolation inside the shell.
                         */

                        withEnv([
                            "FAILED_COMMIT=${env.CURRENT_COMMIT}",
                            "TARGET_GOOD_COMMIT=${goodCommit}"
                        ]) {

                            sh '''
                                set +e

                                cd "${WORKSPACE}"

                                echo "========================================"
                                echo "FETCHING GITHUB"
                                echo "========================================"

                                git fetch origin "${GITHUB_BRANCH}"

                                if [ "$?" -ne 0 ]; then

                                    echo "Git fetch failed."
                                    exit 1

                                fi


                                /*
                                 * Get current remote main.
                                 */

                                REMOTE_COMMIT=$(git rev-parse \
                                    "origin/${GITHUB_BRANCH}")

                                echo ""
                                echo "GitHub main:"
                                echo "${REMOTE_COMMIT}"

                                echo ""
                                echo "Failed commit:"
                                echo "${FAILED_COMMIT}"

                                echo ""
                                echo "Known-good commit:"
                                echo "${TARGET_GOOD_COMMIT}"


                                /*
                                 * SAFETY CHECK
                                 *
                                 * Do not overwrite another commit
                                 * that may have been pushed after
                                 * Jenkins started.
                                 */

                                if [ "${REMOTE_COMMIT}" != "${FAILED_COMMIT}" ]; then

                                    echo ""
                                    echo "========================================"
                                    echo "GITHUB CHANGED"
                                    echo "========================================"

                                    echo ""
                                    echo "The remote main branch no longer"
                                    echo "matches the failed commit."

                                    echo ""
                                    echo "GitHub rollback CANCELLED."

                                    exit 1

                                fi


                                /*
                                 * Verify known-good commit.
                                 */

                                if ! git cat-file -e \
                                    "${TARGET_GOOD_COMMIT}^{commit}"; then

                                    echo ""
                                    echo "Known-good commit does not exist."

                                    exit 1

                                fi


                                /*
                                 * Configure Jenkins Git identity.
                                 */

                                git config user.name "Jenkins"

                                git config user.email "jenkins@localhost"


                                /*
                                 * Reset local repository to the
                                 * known-good version.
                                 */

                                echo ""
                                echo "Resetting to known-good version..."

                                git reset --hard \
                                    "${TARGET_GOOD_COMMIT}"

                                if [ "$?" -ne 0 ]; then

                                    echo ""
                                    echo "Git reset failed."

                                    exit 1

                                fi


                                /*
                                 * Create a rollback commit.
                                 */

                                echo ""
                                echo "Creating rollback commit..."

                                git commit \
                                    --allow-empty \
                                    -m "Jenkins rollback: ${FAILED_COMMIT}"

                                if [ "$?" -ne 0 ]; then

                                    echo ""
                                    echo "Rollback commit failed."

                                    exit 1

                                fi


                                echo ""
                                echo "Rollback commit:"
                                git log -1 --oneline


                                /*
                                 * Push rollback to GitHub.
                                 */

                                echo ""
                                echo "Pushing rollback to GitHub..."

                                git push \
                                    origin \
                                    "HEAD:${GITHUB_BRANCH}"

                                PUSH_STATUS=$?


                                if [ "${PUSH_STATUS}" -eq 0 ]; then

                                    echo ""
                                    echo "========================================"
                                    echo "GITHUB ROLLBACK SUCCESSFUL"
                                    echo "========================================"

                                else

                                    echo ""
                                    echo "========================================"
                                    echo "GITHUB ROLLBACK FAILED"
                                    echo "========================================"

                                    exit 1

                                fi
                            '''
                        }
                    }
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
JENKINS BUILD FINISHED
========================================
'''
        }
    }
}
