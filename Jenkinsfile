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

        ROLLBACK_DONE = "false"
    }


    stages {


        /*
         * ==================================================
         * CHECKOUT FROM GITHUB
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

                    echo ""
                    echo "Checkout completed."
                '''
            }
        }


        /*
         * ==================================================
         * DETECT JENKINS ROLLBACK COMMIT
         * ==================================================
         *
         * Prevents:
         *
         * Jenkins
         *   ↓
         * GitHub revert
         *   ↓
         * webhook
         *   ↓
         * Jenkins
         *   ↓
         * another revert
         *
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

Deployment will be skipped.
Another rollback will NOT be created.
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
         * This runs BEFORE deployment.
         *
         * If PHP syntax fails:
         *
         * - Deploy is never executed.
         * - Existing website remains unchanged.
         * - Post failure will rollback GitHub.
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
                        "import selenium; print(selenium.__version__)"

                    echo ""
                    echo "Chromium:"

                    chromium --version

                    echo ""
                    echo "SELENIUM ENVIRONMENT PASSED"
                '''
            }
        }


        /*
         * ==================================================
         * BACKUP CURRENT WEBSITE
         * ==================================================
         *
         * Only reached after PHP validation passes.
         *
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
                     * Set this BEFORE rsync.
                     *
                     * If rsync partially changes the website
                     * and then fails, rollback will happen.
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
                    echo "DEPLOYMENT COMPLETED"
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
                    echo "HTTP TEST PASSED"
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
         * THIS IS THE IMPORTANT PART.
         *
         * GitHub rollback happens even if the failure
         * occurred during PHP syntax checking.
         *
         */

        failure {

            script {

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
                     * Only restore website if deployment
                     * actually started.
                     *
                     */

                    if (env.DEPLOYED == "true") {

                        echo '''
========================================
DEPLOYMENT FAILED
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
NO DEPLOYMENT WAS PERFORMED
========================================

The existing website was not changed.
'''
                    }


                    /*
                     * ======================================
                     * GITHUB ROLLBACK
                     * ======================================
                     *
                     * This happens for:
                     *
                     * PHP syntax failure
                     * Selenium failure
                     * HTTP failure
                     * deployment failure
                     *
                     */

                    echo '''
========================================
GITHUB ROLLBACK
========================================

Reverting failed commit:
'''

                    echo "${env.CURRENT_COMMIT}"


                    sh '''
                        set +e

                        cd "${WORKSPACE}"

                        echo ""
                        echo "Checking GitHub branch..."

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
                         * Only revert if GitHub still points
                         * to the commit Jenkins tested.
                         */

                        if [ "${REMOTE_COMMIT}" != "${CURRENT_COMMIT}" ]; then

                            echo ""
                            echo "========================================"
                            echo "GITHUB CHANGED SINCE JENKINS CHECKOUT"
                            echo "========================================"

                            echo ""
                            echo "GitHub main is no longer the commit"
                            echo "that Jenkins tested."

                            echo ""
                            echo "GitHub rollback will NOT continue."

                            exit 1
                        fi


                        /*
                         * Configure Jenkins Git identity.
                         */

                        git config user.name "Jenkins"

                        git config user.email "jenkins@localhost"


                        /*
                         * Create revert commit.
                         */

                        echo ""
                        echo "Creating GitHub rollback commit..."

                        git revert \
                            --no-edit \
                            -m 1 \
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
                         * Change commit message so the next
                         * webhook can identify it as an
                         * automatic rollback.
                         */

                        git commit \
                            --amend \
                            -m "Jenkins rollback: ${CURRENT_COMMIT}"

                        AMEND_STATUS=$?


                        if [ "${AMEND_STATUS}" -ne 0 ]; then

                            echo ""
                            echo "Could not create rollback commit."

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
