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

        SKIP_ROLLBACK = "false"

        CURRENT_COMMIT = ""

        GOOD_COMMIT_FILE = "/var/backups/myapp/known_good_commit"
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
                    echo "Current commit:"
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
         * 2. CHECK IF THIS IS AN AUTOMATIC ROLLBACK
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
AUTOMATIC ROLLBACK COMMIT
========================================

This commit was created by Jenkins.

Pipeline will stop here.

No deployment.
No second rollback.
'''

                        env.SKIP_ROLLBACK = "true"

                    } else {

                        env.SKIP_ROLLBACK = "false"
                    }
                }
            }
        }


        /*
         * ==================================================
         * 3. PHP SYNTAX CHECK
         * ==================================================
         *
         * THIS IS BEFORE DEPLOYMENT.
         *
         * If PHP has an error:
         *
         * - Website is NOT changed.
         * - GitHub is rolled back to known-good commit.
         *
         */

        stage('CHECK PHP SYNTAX FIRST') {

            when {

                expression {
                    env.SKIP_ROLLBACK != "true"
                }
            }

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
                        echo "Running PHP syntax check..."
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
                    env.SKIP_ROLLBACK != "true"
                }
            }

            steps {

                sh '''
                    set -e

                    echo "========================================"
                    echo "CHECKING SELENIUM"
                    echo "========================================"

                    ${PYTHON} --version

                    echo ""

                    ${PYTHON} -c \
                        "import selenium; print('Selenium:', selenium.__version__)"

                    echo ""

                    chromium --version

                    echo ""
                    echo "Selenium environment OK."
                '''
            }
        }


        /*
         * ==================================================
         * 5. BACKUP WEBSITE
         * ==================================================
         */

        stage('Backup Current Website') {

            when {

                expression {
                    env.SKIP_ROLLBACK != "true"
                }
            }

            steps {

                sh '''
                    set -e

                    echo "========================================"
                    echo "BACKUP CURRENT WEBSITE"
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
                    env.SKIP_ROLLBACK != "true"
                }
            }

            steps {

                script {

                    /*
                     * Set BEFORE rsync.
                     *
                     * If rsync partially modifies the website,
                     * rollback will still happen.
                     */

                    env.DEPLOYED = "true"
                }

                sh '''
                    set -e

                    echo "========================================"
                    echo "DEPLOYING"
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
                    env.SKIP_ROLLBACK != "true"
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
                    env.SKIP_ROLLBACK != "true"
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
                    echo "SELENIUM TEST PASSED."
                '''
            }
        }


        /*
         * ==================================================
         * 9. MARK CURRENT COMMIT AS KNOWN GOOD
         * ==================================================
         *
         * ONLY happens after:
         *
         * PHP PASS
         * HTTP PASS
         * Selenium PASS
         *
         */

        stage('Mark Version Good') {

            when {

                expression {
                    env.SKIP_ROLLBACK != "true"
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
                    echo "Version marked as GOOD."
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

            script {

                if (env.SKIP_ROLLBACK == "true") {

                    echo '''
========================================
JENKINS ROLLBACK COMMIT
========================================

No deployment performed.
'''

                } else {

                    echo '''
========================================
DEPLOYMENT SUCCESSFUL
========================================

PHP:       PASS
HTTP:      PASS
SELENIUM:  PASS

Current version is now known-good.
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
                 * ------------------------------------------
                 * DO NOTHING FOR AUTOMATIC ROLLBACK COMMIT
                 * ------------------------------------------
                 */

                if (env.SKIP_ROLLBACK == "true") {

                    echo '''
========================================
AUTOMATIC ROLLBACK COMMIT
========================================

Rollback loop prevented.
'''
                }

                else {


                    /*
                     * --------------------------------------
                     * READ LAST KNOWN-GOOD COMMIT
                     * --------------------------------------
                     */

                    def goodCommit = sh(
                        script: """
                            if [ -f '${GOOD_COMMIT_FILE}' ]; then
                                sudo cat '${GOOD_COMMIT_FILE}'
                            fi
                        """,
                        returnStdout: true
                    ).trim()


                    /*
                     * --------------------------------------
                     * WEBSITE ROLLBACK
                     * --------------------------------------
                     */

                    if (env.DEPLOYED == "true") {

                        echo '''
========================================
DEPLOYMENT FAILED
========================================

Restoring previous website...
'''

                        sh '''
                            set +e

                            if [ -d "${BACKUP_CURRENT}" ]; then

                                sudo rsync -a \
                                    --delete \
                                    "${BACKUP_CURRENT}/" \
                                    "${WEB_DIR}/"

                                echo ""
                                echo "WEBSITE ROLLBACK COMPLETED."

                            else

                                echo ""
                                echo "NO WEBSITE BACKUP FOUND."

                            fi
                        '''
                    }

                    else {

                        echo '''
========================================
DEPLOYMENT NEVER STARTED
========================================

Existing website was not modified.
'''
                    }


                    /*
                     * --------------------------------------
                     * GITHUB ROLLBACK
                     * --------------------------------------
                     */

                    if (goodCommit == "") {

                        echo '''
========================================
NO KNOWN-GOOD COMMIT
========================================

This appears to be the first deployment.

GitHub cannot be automatically rolled back
because Jenkins has no saved known-good
version yet.
'''

                    }

                    else if (goodCommit == env.CURRENT_COMMIT) {

                        echo '''
========================================
CURRENT COMMIT IS ALREADY KNOWN GOOD
========================================

No GitHub rollback required.
'''

                    }

                    else {

                        echo '''
========================================
ROLLING BACK GITHUB
========================================
'''

                        echo "Failed commit:"
                        echo "${env.CURRENT_COMMIT}"

                        echo ""

                        echo "Known-good commit:"
                        echo "${goodCommit}"


                        sh """
                            set +e

                            cd '${WORKSPACE}'

                            echo ""
                            echo "Fetching GitHub..."

                            git fetch origin '${GITHUB_BRANCH}'

                            REMOTE_COMMIT=\\$(git rev-parse \
                                'origin/${GITHUB_BRANCH}')

                            echo ""
                            echo "Current GitHub commit:"
                            echo "\\${REMOTE_COMMIT}"

                            echo ""
                            echo "Expected failed commit:"
                            echo '${CURRENT_COMMIT}'


                            if [ "\\${REMOTE_COMMIT}" != '${CURRENT_COMMIT}' ]; then

                                echo ""
                                echo "GitHub changed after Jenkins checkout."

                                echo "Rollback stopped to avoid overwriting"
                                echo "another commit."

                                exit 1
                            fi


                            echo ""
                            echo "Checking known-good commit..."

                            if ! git cat-file -e '${goodCommit}^{commit}'; then

                                echo ""
                                echo "Known-good commit not available."

                                exit 1
                            fi


                            echo ""
                            echo "Creating rollback commit..."

                            git config user.name "Jenkins"

                            git config user.email "jenkins@localhost"


                            git reset --hard '${goodCommit}'


                            git commit \
                                --allow-empty \
                                -m "Jenkins rollback: ${CURRENT_COMMIT}"


                            echo ""
                            echo "Rollback commit:"
                            git log -1 --oneline


                            echo ""
                            echo "Pushing rollback to GitHub..."

                            git push \
                                origin \
                                "HEAD:${GITHUB_BRANCH}"


                            if [ "\\$?" -eq 0 ]; then

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
                        """
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
