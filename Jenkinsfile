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
                    branch: "${GITHUB_BRANCH}",
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
                    echo "Repository files:"
                    find . \
                        -type f \
                        -not -path './.git/*' \
                        -not -path './Jenkinsfile' \
                        | sort

                    echo ""
                    echo "Checkout completed."
                '''
            }
        }


        /*
         * ==================================================
         * 2. SHOW ALL FILES IN THIS PUSH
         * ==================================================
         */

        stage('Check Pushed Files') {

            steps {

                sh '''
                    set -e

                    echo "========================================"
                    echo "FILES IN PUSHED COMMIT"
                    echo "========================================"

                    echo ""
                    echo "Commit:"
                    git rev-parse HEAD

                    echo ""
                    echo "Changed files:"
                    echo "----------------------------------------"

                    git diff-tree \
                        --no-commit-id \
                        --name-status \
                        -r \
                        HEAD

                    echo ""
                    echo "All repository files:"
                    echo "----------------------------------------"

                    find . \
                        -type f \
                        -not -path './.git/*' \
                        | sort

                    echo ""
                    echo "File check completed."
                '''
            }
        }


        /*
         * ==================================================
         * 3. TEST EVERY PHP FILE
         * ==================================================
         *
         * This checks ALL PHP files in the repository.
         *
         * It does NOT only check index.php.
         *
         */

        stage('CHECK ALL PHP FILES') {

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
                        -not -path "${WORKSPACE}/.git/*" \
                        -not -path "${WORKSPACE}@tmp/*" \
                        | wc -l)

                    echo ""
                    echo "PHP files found: ${PHP_COUNT}"

                    if [ "${PHP_COUNT}" -eq 0 ]; then

                        echo ""
                        echo "No PHP files found."

                    else

                        echo ""
                        echo "PHP files to test:"
                        echo "----------------------------------------"

                        find "${WORKSPACE}" \
                            -type f \
                            -name "*.php" \
                            -not -path "${WORKSPACE}/vendor/*" \
                            -not -path "${WORKSPACE}/.git/*" \
                            -not -path "${WORKSPACE}@tmp/*" \
                            | sort

                        echo ""
                        echo "Running PHP syntax checks..."
                        echo "----------------------------------------"

                        PHP_FAILED=0

                        while IFS= read -r -d '' PHP_FILE
                        do

                            echo ""
                            echo "Testing:"
                            echo "${PHP_FILE}"

                            if php -l "${PHP_FILE}"; then

                                echo "PASS: ${PHP_FILE}"

                            else

                                echo "FAIL: ${PHP_FILE}"

                                PHP_FAILED=1

                            fi

                        done < <(
                            find "${WORKSPACE}" \
                                -type f \
                                -name "*.php" \
                                -not -path "${WORKSPACE}/vendor/*" \
                                -not -path "${WORKSPACE}/.git/*" \
                                -not -path "${WORKSPACE}@tmp/*" \
                                -print0
                        )

                        echo ""

                        if [ "${PHP_FAILED}" -ne 0 ]; then

                            echo "========================================"
                            echo "PHP SYNTAX TEST FAILED"
                            echo "========================================"

                            echo ""
                            echo "One or more PHP files contain errors."
                            echo ""
                            echo "DEPLOYMENT WILL NOT START."
                            echo "THE WEBSITE WILL NOT BE CHANGED."
                            echo "GITHUB WILL NOT BE MODIFIED."

                            exit 1
                        fi

                    fi

                    echo ""
                    echo "========================================"
                    echo "ALL PHP FILES PASSED"
                    echo "========================================"
                '''

                script {

                    env.PHP_CHECK_PASSED = "true"
                }
            }
        }


        /*
         * ==================================================
         * 4. CHECK PYTHON / SELENIUM ENVIRONMENT
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
         * 5. BACKUP CURRENT WEBSITE
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
                    echo "Website backup completed."
                '''
            }
        }


        /*
         * ==================================================
         * 6. DEPLOY ENTIRE REPOSITORY
         * ==================================================
         *
         * IMPORTANT:
         *
         * This deploys ALL files.
         *
         * Examples:
         *
         * index.php
         * login.php
         * register.php
         * css/style.css
         * js/app.js
         * images/*
         * pages/*
         * etc.
         *
         * Only Jenkins-specific files are excluded.
         *
         */

        stage('Deploy ALL Repository Files') {

            when {

                expression {
                    env.PHP_CHECK_PASSED == "true"
                }
            }

            steps {

                script {

                    env.DEPLOYED = "true"
                }

                sh '''
                    set -e

                    echo "========================================"
                    echo "DEPLOYING ALL REPOSITORY FILES"
                    echo "========================================"

                    echo ""
                    echo "Source:"
                    echo "${WORKSPACE}/"

                    echo ""
                    echo "Destination:"
                    echo "${WEB_DIR}/"

                    echo ""
                    echo "Files being deployed:"
                    echo "----------------------------------------"

                    find "${WORKSPACE}" \
                        -type f \
                        -not -path "${WORKSPACE}/.git/*" \
                        -not -path "${WORKSPACE}/Jenkinsfile" \
                        -not -path "${WORKSPACE}/tests/*" \
                        -not -path "${WORKSPACE}/vendor/*" \
                        | sort

                    echo ""
                    echo "Starting rsync..."
                    echo "----------------------------------------"

                    sudo rsync -av \
                        --delete \
                        --exclude=".git" \
                        --exclude="Jenkinsfile" \
                        --exclude="tests/" \
                        --exclude="vendor/" \
                        "${WORKSPACE}/" \
                        "${WEB_DIR}/"

                    echo ""
                    echo "========================================"
                    echo "ALL FILES DEPLOYED"
                    echo "========================================"

                    echo ""
                    echo "Server files:"
                    echo "----------------------------------------"

                    sudo find "${WEB_DIR}" \
                        -type f \
                        | sort
                '''
            }
        }


        /*
         * ==================================================
         * 7. VERIFY DEPLOYED PHP FILES
         * ==================================================
         *
         * This checks PHP again AFTER copying it
         * to the server.
         *
         */

        stage('Verify Server PHP Files') {

            when {

                expression {
                    env.DEPLOYED == "true"
                }
            }

            steps {

                sh '''
                    set -e

                    echo "========================================"
                    echo "VERIFYING SERVER PHP FILES"
                    echo "========================================"

                    PHP_FAILED=0

                    while IFS= read -r -d '' PHP_FILE
                    do

                        echo ""
                        echo "Testing server file:"
                        echo "${PHP_FILE}"

                        if php -l "${PHP_FILE}"; then

                            echo "PASS"

                        else

                            echo "FAIL"

                            PHP_FAILED=1

                        fi

                    done < <(
                        sudo find "${WEB_DIR}" \
                            -type f \
                            -name "*.php" \
                            -print0
                    )

                    if [ "${PHP_FAILED}" -ne 0 ]; then

                        echo ""
                        echo "SERVER PHP VERIFICATION FAILED."

                        exit 1
                    fi

                    echo ""
                    echo "========================================"
                    echo "SERVER PHP FILES PASSED"
                    echo "========================================"
                '''
            }
        }


        /*
         * ==================================================
         * 8. HTTP TEST
         * ==================================================
         */

        stage('HTTP Test') {

            when {

                expression {
                    env.DEPLOYED == "true"
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
         * 9. SELENIUM TEST
         * ==================================================
         */

        stage('Python Selenium Test') {

            when {

                expression {
                    env.DEPLOYED == "true"
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

ALL PHP FILES:       PASS
SERVER PHP FILES:    PASS
HTTP TEST:           PASS
SELENIUM TEST:       PASS

ALL REPOSITORY FILES WERE DEPLOYED.

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
                 * ==========================================
                 * PHP FAILED
                 * ==========================================
                 *
                 * IMPORTANT:
                 *
                 * PHP failed BEFORE deployment.
                 *
                 * Therefore:
                 *
                 * - Do NOT rollback website.
                 * - Do NOT push to GitHub.
                 * - Do NOT change GitHub.
                 *
                 */

                if (env.PHP_CHECK_PASSED != "true") {

                    echo '''
========================================
PHP SYNTAX ERROR
========================================

DEPLOYMENT CANCELLED.

The PHP validation failed.

Website:
NOT CHANGED

GitHub:
NOT CHANGED

No rollback commit will be created.

Fix the PHP file and push a new commit.

========================================
'''
                }


                /*
                 * ==========================================
                 * DEPLOYMENT STARTED
                 * ==========================================
                 *
                 * A later test failed.
                 *
                 * Restore the previous website.
                 *
                 */

                else if (env.DEPLOYED == "true") {

                    echo '''
========================================
DEPLOYMENT / TEST FAILED
========================================

Restoring previous website version...
========================================
'''

                    sh '''
                        set +e

                        if [ -d "${BACKUP_CURRENT}" ]; then

                            echo "Restoring backup..."

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

                    echo '''
========================================
GITHUB WAS NOT MODIFIED
========================================

The GitHub repository remains unchanged.

Fix the failing test and push a new commit.
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
