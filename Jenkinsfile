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

        DEPLOYED = "false"

        GITHUB_BRANCH = "main"
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
         * DETECT AUTOMATIC ROLLBACK COMMIT
         * ==================================================
         *
         * If the previous Jenkins build created a rollback
         * commit, do NOT create another rollback loop.
         *
         */

        stage('Check Rollback Commit') {

            steps {

                script {

                    def commitMessage = sh(
                        script: 'git log -1 --pretty=%B',
                        returnStdout: true
                    ).trim()

                    if (commitMessage.startsWith(
                        'Jenkins rollback:'
                    )) {

                        echo '''
========================================
JENKINS ROLLBACK COMMIT DETECTED
========================================

This commit was created automatically
by Jenkins.

Skipping deployment to prevent a
rollback loop.
'''

                        currentBuild.result = 'NOT_BUILT'

                        env.SKIP_PIPELINE = "true"

                    } else {

                        env.SKIP_PIPELINE = "false"
                    }
                }
            }
        }


        /*
         * ==================================================
         * PHP SYNTAX
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

                    sudo rm -rf "${BACKUP_DIR}/new"

                    sudo mkdir -p "${BACKUP_DIR}/new"

                    sudo rsync -a \
                        "${WEB_DIR}/" \
                        "${BACKUP_DIR}/new/"

                    echo ""
                    echo "New backup created."

                    sudo rm -rf "${BACKUP_DIR}/current"

                    sudo mv \
                        "${BACKUP_DIR}/new" \
                        "${BACKUP_DIR}/current"

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
                     * If rsync fails halfway through,
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
ROLLBACK COMMIT DETECTED
========================================

Automatic rollback commit was detected.

No new deployment was performed.
'''

                } else {

                    echo '''
========================================
DEPLOYMENT SUCCESSFUL
========================================

Website and GitHub are using the new version.
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

                if (env.DEPLOYED == "true") {

                    echo '''
========================================
PIPELINE FAILED
========================================

Restoring previous website version...
========================================
'''

                    /*
                     * --------------------------------------
                     * RESTORE WEBSITE
                     * --------------------------------------
                     */

                    sh '''
                        set +e

                        if [ -d "${BACKUP_CURRENT}" ]; then

                            echo "Restoring website..."

                            sudo rsync -a \
                                --delete \
                                "${BACKUP_CURRENT}/" \
                                "${WEB_DIR}/"

                            ROLLBACK_STATUS=$?

                            if [ "${ROLLBACK_STATUS}" -eq 0 ]; then

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


                    /*
                     * --------------------------------------
                     * GITHUB ROLLBACK
                     * --------------------------------------
                     */

                    echo '''
========================================
ROLLING BACK GITHUB
========================================
'''

                    sh '''
                        set +e

                        cd "${WORKSPACE}"

                        echo ""
                        echo "Current bad commit:"
                        git rev-parse HEAD

                        echo ""
                        echo "Creating GitHub revert..."

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

                            git revert \
                                --abort

                            exit 1
                        fi

                        echo ""
                        echo "Revert commit:"
                        git log -1 --oneline

                        echo ""
                        echo "Pushing rollback to GitHub..."

                        git push origin \
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

                } else {

                    echo '''
========================================
PIPELINE FAILED BEFORE DEPLOYMENT
========================================

The website was not changed.

The GitHub repository was not changed.

No rollback was necessary.
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
