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

        SKIP_PIPELINE = "false"

        CURRENT_COMMIT = ""
    }


    stages {


        /*
         * ==================================================
         * CHECKOUT
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
                    echo "Branch:"
                    git branch --show-current

                    echo ""
                    echo "Commit message:"
                    git log -1 --pretty=%B
                '''
            }
        }


        /*
         * ==================================================
         * CHECK FOR JENKINS ROLLBACK COMMIT
         * ==================================================
         */

        stage('Check Rollback Commit') {

            steps {

                script {

                    def commitMessage = sh(
                        script: 'git log -1 --pretty=%B',
                        returnStdout: true
                    ).trim()

                    if (
                        commitMessage.startsWith(
                            'Jenkins rollback:'
                        )
                    ) {

                        echo '''
========================================
JENKINS ROLLBACK COMMIT DETECTED
========================================

This commit was created by Jenkins.

Skipping deployment and rollback.
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
         * PHP SYNTAX CHECK
         * ==================================================
         *
         * IMPORTANT:
         *
         * This happens BEFORE backup and deployment.
         *
         * If PHP is broken:
         *
         *   PHP check FAILS
         *       ↓
         *   Deploy is skipped
         *       ↓
         *   Existing website stays unchanged
         *       ↓
         *   GitHub rollback happens in post/failure
         *
         */

        stage('Check PHP Syntax') {

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
         * SELENIUM ENVIRONMENT
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
                    echo "SELENIUM ENVIRONMENT PASSED"
                    echo "========================================"
                '''
            }
        }


        /*
         * ==================================================
         * BACKUP CURRENT WEBSITE
         * ==================================================
         */

        stage('Backup Current Version') {

            when {

                expression {
                    env.SKIP_PIPELINE != "true"
                }
            }

            steps {

                sh '''
                    set -e

                    echo "========================================"
                    echo "BACKING UP CURRENT VERSION"
                    echo "========================================"

                    sudo mkdir -p "${BACKUP_DIR}"

                    sudo rm -rf \
                        "${BACKUP_DIR}/new"

                    sudo mkdir -p \
                        "${BACKUP_DIR}/new"

                    sudo rsync -a \
                        "${WEB_DIR}/" \
                        "${BACKUP_DIR}/new/"

                    echo ""
                    echo "New backup created."

                    sudo rm -rf \
                        "${BACKUP_CURRENT}"

                    sudo mv \
                        "${BACKUP_DIR}/new" \
                        "${BACKUP_CURRENT}"

                    echo ""
                    echo "========================================"
                    echo "BACKUP READY"
                    echo "========================================"
                '''
            }
        }


        /*
         * ==================================================
         * DEPLOY
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
                     * Mark deployment BEFORE rsync.
                     *
                     * This ensures that a partial rsync failure
                     * also triggers website rollback.
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
                    echo "========================================"
                    echo "DEPLOYMENT COMPLETED"
                    echo "========================================"
                '''
            }
        }


        /*
         * ==================================================
         * HTTP TEST
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
                    echo "========================================"
                    echo "HTTP TEST PASSED"
                    echo "========================================"
                '''
            }
        }


        /*
         * ==================================================
         * SELENIUM TEST
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
                        echo "tests/selenium_test.py does not exist."

                        exit 1
                    fi

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

            script {

                if (env.SKIP_PIPELINE == "true") {

                    echo '''
========================================
JENKINS ROLLBACK COMMIT
========================================

Rollback commit detected.

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

The new version is live.
'''
                }
            }
        }


        /*
         * ==================================================
         * FAILURE
         * ==================================================
         *
         * This executes for:
         *
         * - PHP syntax errors
         * - Selenium errors
         * - HTTP errors
         * - deployment errors
         * - environment errors
         *
         */

        failure {

            script {

                /*
                 * Never rollback a rollback commit.
                 */

                if (env.SKIP_PIPELINE == "true") {

                    echo '''
========================================
ROLLBACK COMMIT
========================================

No second rollback will be performed.
'''

                } else {


                    /*
                     * ======================================
                     * WEBSITE ROLLBACK
                     * ======================================
                     *
                     * If deployment started, restore
                     * the previous website.
                     *
                     */

                    if (env.DEPLOYED == "true") {

                        echo '''
========================================
PIPELINE FAILED
========================================

Restoring previous website version...
========================================
'''

                        sh '''
                            set +e

                            if [ -d "${BACKUP_CURRENT}" ]; then

                                echo ""
                                echo "Restoring website..."

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
NO WEBSITE DEPLOYMENT
========================================

The existing website was not changed.
'''
                    }


                    /*
                     * ======================================
                     * GITHUB ROLLBACK
                     * ======================================
                     *
                     * IMPORTANT:
                     *
                     * This runs even when PHP syntax
                     * checking fails.
                     *
                     */

                    echo '''
========================================
GITHUB ROLLBACK
========================================

Failed commit:
'''

                    echo "${env.CURRENT_COMMIT}"


                    sh '''
                        set +e

                        cd "${WORKSPACE}"

                        echo ""
                        echo "Fetching latest GitHub main..."

                        git fetch origin "${GITHUB_BRANCH}"

                        REMOTE_COMMIT=$(git rev-parse \
                            "origin/${GITHUB_BRANCH}")

                        echo ""
                        echo "Commit Jenkins tested:"
                        echo "${CURRENT_COMMIT}"

                        echo ""
                        echo "Current GitHub main:"
                        echo "${REMOTE_COMMIT}"


                        /*
                         * Safety check.
                         *
                         * Only revert if nobody has pushed
                         * another commit since Jenkins checked
                         * this commit.
                         */

                        if [ "${REMOTE_COMMIT}" != "${CURRENT_COMMIT}" ]; then

                            echo ""
                            echo "========================================"
                            echo "GITHUB CHANGED"
                            echo "========================================"

                            echo ""
                            echo "GitHub main no longer points to the"
                            echo "commit Jenkins tested."

                            echo ""
                            echo "GitHub rollback cancelled for safety."

                            exit 1
                        fi


                        /*
                         * Configure Git identity.
                         */

                        git config user.name "Jenkins"

                        git config user.email "jenkins@localhost"


                        /*
                         * ==================================
                         * REVERT NORMAL COMMIT
                         * ==================================
                         *
                         * DO NOT use:
                         *
                         * git revert -m 1
                         *
                         * for normal commits.
                         */

                        echo ""
                        echo "Creating GitHub rollback..."

                        git revert \
                            --no-edit \
                            "${CURRENT_COMMIT}"

                        REVERT_STATUS=$?


                        if [ "${REVERT_STATUS}" -ne 0 ]; then

                            echo ""
                            echo "========================================"
                            echo "GITHUB REVERT FAILED"
                            echo "========================================"

                            git revert --abort

                            exit 1
                        fi


                        /*
                         * Give the rollback commit a recognizable
                         * message so Jenkins can detect it when
                         * the GitHub webhook fires.
                         */

                        git commit \
                            --amend \
                            -m "Jenkins rollback: ${CURRENT_COMMIT}"

                        AMEND_STATUS=$?


                        if [ "${AMEND_STATUS}" -ne 0 ]; then

                            echo ""
                            echo "========================================"
                            echo "ROLLBACK COMMIT FAILED"
                            echo "========================================"

                            exit 1
                        fi


                        echo ""
                        echo "Rollback commit created:"

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
