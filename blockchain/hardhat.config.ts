import { HardhatUserConfig } from "hardhat/config";

const config: HardhatUserConfig = {
  solidity: {
    version: "0.8.20",
    settings: {
      evmVersion: "paris" // ✅ compatible with Ganache
    }
  },
  networks: {
    ganache: {
      type: "http",
      url: "http://127.0.0.1:7545",
      accounts: [
        "0x64880b7eb8c4f56d9333030a6c83e592c6b5ff59a167e7f209ed137004dc385e"
      ],
    },
  },
};

export default config;
