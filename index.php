pipeline {

<<<<<<< HEAD
echo '<!DOCTYPE html>' 
=======
    agent any
>>>>>>> 1cfaad063baa1bf1a43623c89f320acc35eb4432

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

        GOOD_COMMIT_FILE = "/var/backups/myapp/good_commit"

        PYTHON = "/opt/selenium-venv/bin/python"

        GITHUB_BRANCH = "main"

        DEPLOYED = "false"

        ROLLBACK_NEEDED = "false"

        SKIP_PIPELINE = "false"
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
                    echo "Branch:"
                    git branch --show-current
                '''
            }
        }


        /*
         * ==================================================
         * CHECK WHETHER THIS IS A JENKINS ROLLBACK COMMIT
         * ==================================================
         *
         * Prevents:
         *
         * Jenkins rollback
         *      ↓
         * GitHub push
         *      ↓
         * GitHub webhook
         *      ↓
         * Jenkins
         *      ↓
         * rollback again
         *
         */

        stage('Check Automatic Rollback') {

            steps {

                script {

                    def message = sh(
                        script: 'git log -1 --pretty=%B',
                        returnStdout: true
                    ).trim()

                    if (
                        message.startsWith(
                            'Jenkins rollback:'
                        )
                    ) {

                        echo '''
========================================
AUTOMATIC ROLLBACK COMMIT DETECTED
========================================

This commit was created by Jenkins.

No deployment will be performed.
No additional rollback will be created.
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
         * SAVE CURRENT COMMIT
         * ==================================================
         */

        stage('Get Commit Information') {

            when {

                expression {
                    env.SKIP_PIPELINE != "true"
                }
            }

            steps {

                script {

                    env.CURRENT_COMMIT = sh(
                        script: 'git rev-parse HEAD',
                        returnStdout: true
                    ).trim()

                    echo """
========================================
CURRENT GITHUB COMMIT
========================================

${env.CURRENT_COMMIT}
"""
                }
            }
        }


        /*
         * ==================================================
         * CHECK FOR PREVIOUS KNOWN-GOOD COMMIT
         * ==================================================
         */

        stage('Check Known Good Version') {

            when {

                expression {
                    env.SKIP_PIPELINE != "true"
                }
            }

            steps {

                sh '''
                    set -e

                    echo "========================================"
                    echo "KNOWN GOOD VERSION"
                    echo "========================================"

                    if [ -f "${GOOD_COMMIT_FILE}" ]; then

                        echo ""
                        echo "Previous known-good commit:"

                        sudo cat "${GOOD_COMMIT_FILE}"

                    else

                        echo ""
                        echo "No known-good commit file exists yet."

                        echo "This may be the first deployment."

                    fi
                '''
            }
        }


        /*
         * ==================================================
         * PHP SYNTAX CHECK
         * ==================================================
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

                    ${PYTHON} --version

                    echo ""

                    ${PYTHON} -c \
                        "import selenium; print('Selenium:', selenium.__version__)"

                    echo ""

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

                    echo ""
                    echo "Backup completed."

                    sudo rm -rf "${BACKUP_CURRENT}"

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
                     * Mark as deployed BEFORE rsync.
                     *
                     * Therefore even a partial rsync failure
                     * will trigger rollback.
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


        /*
         * ==================================================
         * MARK NEW VERSION AS KNOWN-GOOD
         * ==================================================
         *
         * ONLY reached after:
         *
         * PHP PASS
         * HTTP PASS
         * Selenium PASS
         *
         */

        stage('Mark Version As Good') {

            when {

                expression {
                    env.SKIP_PIPELINE != "true"
                }
            }

            steps {

                sh '''
                    set -e

                    echo "========================================"
                    echo "MARKING VERSION AS KNOWN-GOOD"
                    echo "========================================"

                    echo "${CURRENT_COMMIT}" | \
                        sudo tee "${GOOD_COMMIT_FILE}" > /dev/null

                    echo ""
                    echo "Known-good commit:"

                    sudo cat "${GOOD_COMMIT_FILE}"

                    echo ""
                    echo "========================================"
                    echo "VERSION MARKED GOOD"
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
ROLLBACK COMMIT
========================================

Jenkins rollback commit detected.

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

The new GitHub commit is now known-good.

The new website remains deployed.
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
                 * ==========================================
                 * STEP 1
                 * RESTORE WEBSITE
                 * ==========================================
                 */

                if (env.DEPLOYED == "true") {

                    echo '''
========================================
PIPELINE FAILED
========================================

RESTORING PREVIOUS WEBSITE VERSION
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
                 * ==========================================
                 * STEP 2
                 * GITHUB ROLLBACK
                 * ==========================================
                 *
                 * Only perform GitHub rollback for a real
                 * failed build.
                 *
                 */

                if (
                    env.SKIP_PIPELINE != "true" &&
                    env.CURRENT_COMMIT
                ) {

                    echo '''
========================================
GITHUB ROLLBACK
========================================
'''

                    sh '''
                        set +e

                        cd "${WORKSPACE}"

                        echo ""
                        echo "Bad commit:"
                        git rev-parse HEAD

                        echo ""
                        echo "Creating revert commit..."

                        git config user.name "Jenkins"

                        git config user.email "jenkins@localhost"

                        git revert \
                            --no-edit \
                            HEAD

                        REVERT_STATUS=$?

                        if [ "${REVERT_STATUS}" -ne 0 ]; then

                            echo ""
                            echo "========================================"
                            echo "GITHUB REVERT FAILED"
                            echo "========================================"

                            git revert --abort

                            exit 1
                        fi

                        echo ""
                        echo "Rollback commit created:"

                        git log -1 --oneline

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
