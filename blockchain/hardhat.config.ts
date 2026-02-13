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
        "0x99d8f2202e03882bb4387fd8fbcfa8916e9f7d9bd9f4f2f37483d6bc90f482cd"
      ],
    },
  },
};

export default config;
